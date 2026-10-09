<?php

namespace Tests\Feature;

use App\Filament\Pages\AppSettings;
use App\Filament\Pages\CloudBackups;
use App\Filament\Pages\GoogleFirebaseSettings;
use App\Filament\Pages\GoshenReferralSettings;
use App\Filament\Pages\GoshenTicketPdfTemplates;
use App\Filament\Pages\PaymentGateways;
use App\Filament\Resources\AddonResource;
use App\Filament\Resources\AiProviderSettingResource;
use App\Filament\Resources\RoleResource;
use App\Models\AdminMenuRoleVisibility;
use App\Models\User;
use App\Support\AdminAccessReport;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use Filament\Pages\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DelegatedSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_settings_destination_is_independent_of_the_hub_and_respects_visibility(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('selected_settings', 'web');
        $user->assignRole($role);
        AdminMenuRoleVisibility::create(['role_id' => $role->id, 'menu_key' => AdminMenuRegistry::pageKey(AppSettings::class), 'is_visible' => false]);
        foreach ([
            PaymentGateways::class => AdminPermissions::PAYMENT_GATEWAYS,
            CloudBackups::class => AdminPermissions::CLOUD_BACKUPS,
            GoogleFirebaseSettings::class => AdminPermissions::GOOGLE_FIREBASE,
            GoshenReferralSettings::class => AdminPermissions::REFERRAL_SETTINGS,
            GoshenTicketPdfTemplates::class => AdminPermissions::TICKET_PDF_SETTINGS,
            RoleResource::class => 'manage_role',
            AiProviderSettingResource::class => 'manage_ai_provider_setting',
            AddonResource::class => 'manage_addon',
        ] as $class => $name) {
            $role->syncPermissions([Permission::findOrCreate($name, 'web')]);
            $this->actingAs($user->fresh());
            $this->assertTrue($class::shouldRegisterNavigation(), $class);
            $this->assertFalse(AppSettings::canAccess());
            $this->get('/admin/app-settings-hub')->assertForbidden();
            $isPage = is_subclass_of($class, Page::class);
            $key = $isPage ? AdminMenuRegistry::pageKey($class) : AdminMenuRegistry::resourceKey($class);
            $this->assertContains($key, array_column(AdminMenuRegistry::items(), 'key'));
            AdminMenuRoleVisibility::create(['role_id' => $role->id, 'menu_key' => $key, 'is_visible' => false]);
            $this->assertFalse($class::shouldRegisterNavigation());
            $this->assertNotContains($class::getUrl(), array_column(AdminMenuRegistry::settingsQuickLinks(), 'url'));
            $this->assertTrue($isPage ? $class::canAccess() : $class::canViewAny());
            $role->syncPermissions([]);
            $this->actingAs($user->fresh());
            $this->assertFalse($isPage ? $class::canAccess() : $class::canViewAny());
        }
    }

    public function test_page_save_is_denied_after_permission_revocation(): void
    {
        $user = User::factory()->create();
        $permission = Permission::findOrCreate(AdminPermissions::REFERRAL_SETTINGS, 'web');
        $user->givePermissionTo($permission);
        $component = Livewire::actingAs($user)->test(GoshenReferralSettings::class)->assertOk();
        $user->revokePermissionTo($permission);
        $component->call('save')->assertForbidden();
    }

    public function test_settings_migration_maps_existing_role_and_direct_access_without_broadening_other_roles(): void
    {
        $previous = Permission::findOrCreate('manage_app_setting', 'web');
        $role = Role::findOrCreate('settings_admin', 'web');
        $role->givePermissionTo($previous);
        $other = Role::findOrCreate('other', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo($previous);
        $migration = require database_path('migrations/2026_10_09_180100_add_delegated_settings_permissions.php');
        $migration->up();
        $migration->up();
        foreach ([AdminPermissions::GOOGLE_FIREBASE, AdminPermissions::REFERRAL_SETTINGS] as $name) {
            $this->assertTrue($role->fresh()->hasPermissionTo($name));
            $this->assertTrue($user->fresh()->can($name));
            $this->assertFalse($other->fresh()->hasPermissionTo($name));
        }
        $this->assertFalse($user->fresh()->can(AdminPermissions::TICKET_PDF_SETTINGS));
    }

    public function test_effective_access_report_explains_additive_grants_and_retains_unknown_names(): void
    {
        $permission = Permission::findOrCreate('manage_donation', 'web');
        $legacy = Permission::findOrCreate('legacy_unknown', 'web');
        $role = Role::findOrCreate('finance_test', 'web');
        $role->givePermissionTo([$permission, $legacy]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo($permission);
        $report = AdminAccessReport::forUser($user);
        $grant = collect($report['permissions'])->firstWhere('name', $permission->name);
        $this->assertTrue($grant['direct']);
        $this->assertSame(['finance_test'], $grant['roles']);
        $this->assertFalse(collect($report['permissions'])->firstWhere('name', $legacy->name)['catalogued']);
        $role->revokePermissionTo($permission);
        $this->assertTrue($user->fresh()->can($permission->name));
        $this->artisan('admin-permissions:explain', ['user' => $user->id])->assertSuccessful();
        $this->assertTrue($role->fresh()->hasPermissionTo($legacy));
    }
}
