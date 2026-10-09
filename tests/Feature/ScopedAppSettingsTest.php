<?php

namespace Tests\Feature;

use App\Filament\Pages\AppSettings;
use App\Filament\Resources\AppSettingResource;
use App\Filament\Resources\AppSettingResource\Pages\EditAppSetting;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\AppSettingsSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopedAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $sections = [], bool $super = false): User
    {
        $user = User::factory()->create();
        if ($super) {
            $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        } else {
            $user->givePermissionTo(Permission::findOrCreate(AppSettingsSections::HUB_PERMISSION, 'web'));
            foreach ($sections as $section) {
                $user->givePermissionTo(Permission::findOrCreate(AppSettingsSections::permission($section), 'web'));
            }
        }
        return $user;
    }

    public function test_hub_only_does_not_load_or_render_protected_settings(): void
    {
        AppSetting::create(['key' => 'app_name', 'group' => 'branding', 'value' => 'Private branding value']);
        AppSetting::create(['key' => 'private_test_key', 'value' => 'DO_NOT_EXPOSE', 'is_secret' => true]);
        Livewire::actingAs($this->admin())->test(AppSettings::class)
            ->assertSet('appName', '')->assertSet('additionalSettings', [])
            ->assertDontSee('Private branding value')->assertDontSee('DO_NOT_EXPOSE')
            ->assertDontSee('wire:model.defer="appName"', false)
            ->call('save', 'branding')->assertForbidden();
    }

    public function test_section_saves_only_its_allowlist_and_audits_changes(): void
    {
        AppSetting::create(['key' => 'app_name', 'group' => 'branding', 'value' => 'Original']);
        AppSetting::create(['key' => 'currency', 'group' => 'general', 'value' => 'GBP']);
        AppSetting::updateOrCreate(['key' => 'goshen_referrals_enabled'], ['group' => 'features', 'value' => '0']);
        Livewire::actingAs($this->admin(['branding']))->test(AppSettings::class)
            ->set('appName', 'Updated')->call('save', 'branding')->assertHasNoErrors();
        $this->assertSame('Updated', AppSetting::value('app_name'));
        $this->assertSame('GBP', AppSetting::value('currency'));
        $this->assertSame('0', AppSetting::value('goshen_referrals_enabled'));
        $this->assertDatabaseHas('admin_access_audits', ['target_type' => 'app_setting', 'action' => 'updated']);
    }

    public function test_cross_section_and_additional_settings_forgery_are_denied(): void
    {
        $user = $this->admin(['branding']);
        Livewire::actingAs($user)->test(AppSettings::class)->set('currency', 'USD')->assertForbidden();
        Livewire::actingAs($user)->test(AppSettings::class)
            ->set('additionalSettings.stripe_mode.value', 'test')->assertForbidden();
    }

    public function test_super_admin_cannot_inject_a_focused_settings_key_into_other(): void
    {
        AppSetting::create(['key' => 'stripe_mode', 'value' => 'live']);
        Livewire::actingAs($this->admin(super: true))->test(AppSettings::class)
            ->set('additionalSettings.stripe_mode.value', 'test')->call('save', 'other')->assertForbidden();
        $this->assertSame('live', AppSetting::value('stripe_mode'));
    }

    public function test_raw_editor_is_super_admin_only_and_never_hydrates_saved_secrets(): void
    {
        $setting = AppSetting::create(['key' => 'sample_secret', 'group' => 'custom', 'value' => 'DO_NOT_EXPOSE', 'is_secret' => true]);
        $user = $this->admin(['branding']);
        $user->givePermissionTo(Permission::findOrCreate('manage_app_setting', 'web'));
        $this->actingAs($user);
        $this->assertFalse(AppSettingResource::canViewAny());
        Livewire::actingAs($user)->test(EditAppSetting::class, ['record' => $setting->id])->assertForbidden();
        $page = Livewire::actingAs($this->admin(super: true))->test(EditAppSetting::class, ['record' => $setting->id])
            ->assertSet('data.text_value', null)->assertSet('data.value', null)->assertDontSee('DO_NOT_EXPOSE');
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame('DO_NOT_EXPOSE', $setting->fresh()->value);
        $page->fillForm(['text_value' => 'REPLACED_SECRET'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('REPLACED_SECRET', $setting->fresh()->value);
        $audit = DB::table('admin_access_audits')->where('target_type', 'app_setting')->latest('id')->first();
        $this->assertStringNotContainsString('DO_NOT_EXPOSE', $audit->before);
        $this->assertStringNotContainsString('REPLACED_SECRET', $audit->after);
    }

    public function test_revoking_section_access_blocks_an_already_open_editor(): void
    {
        $user = $this->admin(['branding']);
        $page = Livewire::actingAs($user)->test(AppSettings::class);
        $user->revokePermissionTo(AppSettingsSections::permission('branding'));
        Auth::setUser($user->fresh());
        $page->call('save', 'branding')->assertForbidden();
    }

    public function test_role_grants_allow_sections_and_hub_revocation_blocks_open_pages(): void
    {
        $role = Role::findOrCreate('settings_operator', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate(AppSettingsSections::HUB_PERMISSION, 'web'),
            Permission::findOrCreate(AppSettingsSections::permission('support'), 'web'),
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $page = Livewire::actingAs($user)->test(AppSettings::class)
            ->set('accommodationSupportName', 'Help desk')->call('save', 'support')->assertHasNoErrors();
        $this->assertSame('Help desk', AppSetting::value('accommodation_booking_support_name'));
        $role->revokePermissionTo(AppSettingsSections::HUB_PERMISSION);
        Auth::setUser($user->fresh());
        $page->call('save', 'support')->assertForbidden();
    }

    public function test_legacy_migration_adds_only_hub_access_and_keeps_existing_grants(): void
    {
        $legacy = Permission::findOrCreate('manage_app_setting', 'web');
        $role = Role::findOrCreate('legacy_settings_operator', 'web');
        $role->givePermissionTo($legacy);
        $user = User::factory()->create();
        $user->givePermissionTo($legacy);
        $migration = require database_path('migrations/2026_10_09_230000_add_scoped_app_settings_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertTrue($role->fresh()->hasPermissionTo(AppSettingsSections::HUB_PERMISSION));
        $this->assertTrue($user->fresh()->hasDirectPermission(AppSettingsSections::HUB_PERMISSION));
        $this->assertTrue($role->fresh()->hasPermissionTo($legacy));
        foreach (array_keys(AppSettingsSections::sections()) as $section) {
            $this->assertFalse($role->fresh()->hasPermissionTo(AppSettingsSections::permission($section)));
            $this->assertFalse($user->fresh()->hasPermissionTo(AppSettingsSections::permission($section)));
        }
        $this->assertSame(2, DB::table('admin_access_audits')->where('action', 'settings_hub_migrated')->count());
    }

    public function test_secret_toggle_keys_are_blank_and_preserved_in_maintenance(): void
    {
        $setting = AppSetting::updateOrCreate(['key' => 'goshen_referrals_enabled'], ['group' => 'features', 'value' => '1', 'is_secret' => true]);
        Livewire::actingAs($this->admin(super: true))->test(EditAppSetting::class, ['record' => $setting->id])
            ->assertSet('data.text_value', null)->call('save')->assertHasNoFormErrors();
        $this->assertSame('1', $setting->fresh()->value);
    }

    public function test_other_settings_preserves_blank_secrets_and_rejects_protected_known_keys(): void
    {
        AppSetting::create(['key' => 'custom_secret', 'group' => 'custom', 'value' => 'KEEP_SECRET', 'is_secret' => true]);
        AppSetting::create(['key' => 'app_name', 'group' => 'branding', 'value' => 'PRIVATE_NAME', 'is_secret' => true]);
        $page = Livewire::actingAs($this->admin(super: true))->test(AppSettings::class)
            ->assertDontSee('KEEP_SECRET')->assertDontSee('PRIVATE_NAME')
            ->assertSet('additionalSettings.custom_secret.value', '')
            ->call('save', 'other')->assertHasNoErrors();
        $this->assertSame('KEEP_SECRET', AppSetting::value('custom_secret'));
        $page->call('save', 'branding')->assertForbidden();
        $this->assertSame('PRIVATE_NAME', AppSetting::value('app_name'));
    }
}
