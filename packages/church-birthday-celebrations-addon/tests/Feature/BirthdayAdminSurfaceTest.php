<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\Addons\AddonRuntimeLoader;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayCelebrationResource;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayGreetingResource;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayLifecycleResource;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayPreferenceResource;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdaySettingResource;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource\Pages\CreateBirthdayTemplate;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayVerseResource;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayGreeting;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BirthdayAdminSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private string $installPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['addons.install_path' => 'addons/testing-birthday-admin']);
        $this->installPath = base_path('addons/testing-birthday-admin/church_birthday_celebrations');
        File::deleteDirectory(dirname($this->installPath));
        File::copyDirectory(base_path('packages/church-birthday-celebrations-addon'), $this->installPath);
        $manifest = json_decode((string) File::get($this->installPath.'/addon.json'), true, flags: JSON_THROW_ON_ERROR);
        Addon::query()->create([
            'package_key' => $manifest['package_key'], 'name' => $manifest['name'], 'installed_version' => $manifest['version'],
            'status' => Addon::STATUS_ACTIVE, 'manifest' => $manifest, 'install_path' => $this->installPath,
        ]);
        app(AddonRuntimeLoader::class)->registerAddon($manifest, $this->installPath);
        Route::get('/testing/church-birthday-celebrations/moderation', fn () => null)
            ->name('filament.admin.resources.church-birthday-celebrations.moderation.index');
        Route::get('/testing/church-birthday-celebrations/templates', fn () => null)
            ->name('filament.admin.resources.church-birthday-celebrations.templates.index');
        Route::get('/testing/church-birthday-celebrations/templates/{record}/edit', fn () => null)
            ->name('filament.admin.resources.church-birthday-celebrations.templates.edit');
        $this->artisan('migrate', ['--path' => $this->installPath.'/database/migrations', '--realpath' => true, '--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->installPath));
        parent::tearDown();
    }

    public function test_each_admin_surface_requires_its_specific_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(BirthdayTemplateResource::canViewAny());
        $this->assertFalse(BirthdayVerseResource::canViewAny());
        $this->assertFalse(BirthdayPreferenceResource::canViewAny());
        $this->assertFalse(BirthdaySettingResource::canViewAny());
        $this->assertFalse(BirthdayCelebrationResource::canViewAny());
        $this->assertFalse(BirthdayGreetingResource::canViewAny());
        $this->assertFalse(BirthdayLifecycleResource::canViewAny());

        $role = Role::findOrCreate('birthday_moderator', 'web');
        $role->givePermissionTo(Permission::findOrCreate('church_birthday_celebrations.moderate', 'web'));
        $user->assignRole($role);
        $this->assertTrue(BirthdayCelebrationResource::canViewAny());
        $this->assertTrue(BirthdayGreetingResource::canViewAny());
        $this->assertFalse(BirthdayTemplateResource::canViewAny());
        $this->assertFalse(BirthdayLifecycleResource::canViewAny());

        $role->givePermissionTo(Permission::findOrCreate('church_birthday_celebrations.recover', 'web'));
        $this->assertTrue(BirthdayLifecycleResource::canViewAny());
    }

    public function test_in_use_template_cannot_be_deleted_and_selected_template_is_deterministic(): void
    {
        $admin = $this->admin('church_birthday_celebrations.manage');
        $this->actingAs($admin);
        $fallback = BirthdayTemplate::query()->create(['name' => 'Fallback', 'sort_order' => 1, 'version' => 2, 'is_active' => true]);
        BirthdayTemplate::query()->create(['name' => 'Later', 'sort_order' => 1, 'version' => 1, 'is_active' => true]);
        $default = BirthdayTemplate::query()->create(['name' => 'Default', 'sort_order' => 99, 'version' => 1, 'is_default' => true, 'is_active' => true]);
        $member = $this->member();

        BirthdayCelebration::query()->create([
            'mobile_user_id' => $member->id, 'template_id' => $fallback->id, 'birthday_year' => 2026,
            'birthday_date' => '2026-07-30', 'display_name' => $member->name,
        ]);

        $this->assertSame($default->id, BirthdayTemplate::selected()?->id);
        $this->assertFalse(BirthdayTemplateResource::canDelete($fallback));
        $this->assertTrue(BirthdayTemplateResource::canDelete($default));

        $default->update(['is_active' => false]);
        $this->assertSame($fallback->id, BirthdayTemplate::selected()?->id);
    }

    public function test_admin_can_create_a_template_from_the_explained_card_design_form(): void
    {
        $admin = $this->admin('church_birthday_celebrations.manage');

        Livewire::actingAs($admin)
            ->test(CreateBirthdayTemplate::class)
            ->assertSee('Create a reusable birthday card style')
            ->assertSee('Use as the default template')
            ->assertSee('Live card preview')
            ->fillForm([
                'name' => 'Gold celebration',
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'background_color' => '#4A2E62',
                'accent_color' => '#D49A2A',
                'verse' => 'May the Lord bless you and keep you.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('birthday_celebration_templates', [
            'name' => 'Gold celebration',
            'is_active' => true,
            'is_default' => true,
            'version' => 1,
        ]);
    }

    public function test_moderator_can_hide_a_reported_greeting_without_deleting_the_audit_record(): void
    {
        $admin = $this->admin('church_birthday_celebrations.moderate');
        $celebration = BirthdayCelebration::query()->create([
            'mobile_user_id' => $this->member()->id, 'birthday_year' => 2026, 'birthday_date' => '2026-07-30',
            'status' => BirthdayCelebration::PUBLISHED, 'display_name' => 'Celebrant', 'closes_at' => now()->addDay(),
        ]);
        $greeting = BirthdayGreeting::query()->create(['celebration_id' => $celebration->id, 'mobile_user_id' => $this->member()->id, 'body' => 'Happy birthday']);

        Livewire::actingAs($admin)->test(\ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayGreetingResource\Pages\ListBirthdayGreetings::class)
            ->callTableAction('hide', $greeting)
            ->assertHasNoTableActionErrors();

        $this->assertSame('hidden', $greeting->fresh()->status);
        $this->assertNotNull($greeting->fresh()->hidden_at);
    }

    private function admin(string $permission): User
    {
        $role = Role::findOrCreate('birthday_admin_'.str_replace('.', '_', $permission), 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function member(): MobileUser
    {
        $number = MobileUser::query()->count() + 1;

        return MobileUser::query()->create([
            'name' => "Member {$number}", 'email' => "member-{$number}@example.test", 'password' => 'secret',
            'member_type' => 'church_member', 'is_verified' => true, 'triumphant_id' => "TM{$number}",
            'birthday_month' => 7, 'birthday_day' => 30,
        ]);
    }
}
