<?php

namespace Tests\Feature;

use App\Filament\Pages\AdminMenuSettings;
use App\Filament\Pages\AppSettings;
use App\Filament\Pages\CloudBackups;
use App\Filament\Pages\CronJobs;
use App\Filament\Pages\GoshenReferralSettings;
use App\Filament\Pages\GoshenRetreatConsole;
use App\Filament\Resources\AccommodationBookingResource;
use App\Filament\Resources\AccommodationPaymentResource;
use App\Filament\Resources\AccommodationUnitResource;
use App\Filament\Resources\AppSettingResource;
use App\Filament\Resources\DonationResource;
use App\Filament\Resources\GoshenBookingResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\AdminMenuRoleVisibility;
use App\Models\User;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_visibility_hides_resource_navigation_without_revoking_permission(): void
    {
        [$admin, $role] = $this->adminWithRolePermission(
            AdminPermissions::resourcePermission(DonationResource::class),
        );

        $this->actingAs($admin);

        $this->assertTrue(DonationResource::canViewAny());
        $this->assertTrue(DonationResource::shouldRegisterNavigation());

        AdminMenuRoleVisibility::query()->create([
            'role_id' => $role->id,
            'menu_key' => AdminMenuRegistry::resourceKey(DonationResource::class),
            'is_visible' => false,
        ]);

        $this->assertTrue(DonationResource::canViewAny());
        $this->assertFalse(DonationResource::shouldRegisterNavigation());
    }

    public function test_menu_visibility_hides_custom_page_navigation_without_revoking_access(): void
    {
        [$admin, $role] = $this->adminWithRolePermission(AdminPermissions::CRON_MONITOR);

        $this->actingAs($admin);

        $this->assertTrue(CronJobs::canAccess());
        $this->assertTrue(CronJobs::shouldRegisterNavigation());

        AdminMenuRoleVisibility::query()->create([
            'role_id' => $role->id,
            'menu_key' => AdminMenuRegistry::pageKey(CronJobs::class),
            'is_visible' => false,
        ]);

        $this->assertTrue(CronJobs::canAccess());
        $this->assertFalse(CronJobs::shouldRegisterNavigation());
    }

    public function test_explicit_hidden_visibility_is_not_reopened_by_an_unconfigured_second_role(): void
    {
        [$admin, $role] = $this->adminWithRolePermission(
            AdminPermissions::resourcePermission(DonationResource::class),
        );
        $secondRole = Role::query()->create([
            'name' => 'unconfigured_secondary_role',
            'guard_name' => 'web',
        ]);
        $admin->assignRole($secondRole);

        AdminMenuRoleVisibility::query()->create([
            'role_id' => $role->id,
            'menu_key' => AdminMenuRegistry::resourceKey(DonationResource::class),
            'is_visible' => false,
        ]);

        $this->actingAs($admin);

        $this->assertTrue(DonationResource::canViewAny());
        $this->assertFalse(DonationResource::shouldRegisterNavigation());
    }

    public function test_menu_visibility_hides_super_admin_navigation_without_revoking_access(): void
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin);

        $this->assertTrue(DonationResource::canViewAny());
        $this->assertTrue(DonationResource::shouldRegisterNavigation());

        AdminMenuRoleVisibility::query()->create([
            'role_id' => $role->id,
            'menu_key' => AdminMenuRegistry::resourceKey(DonationResource::class),
            'is_visible' => false,
        ]);

        $this->assertTrue(DonationResource::canViewAny());
        $this->assertFalse(DonationResource::shouldRegisterNavigation());
    }

    public function test_explicit_hidden_visibility_takes_precedence_over_an_explicitly_visible_second_role(): void
    {
        [$admin, $hiddenRole] = $this->adminWithRolePermission(
            AdminPermissions::resourcePermission(DonationResource::class),
        );
        $visibleRole = Role::query()->create([
            'name' => 'explicitly_visible_secondary_role',
            'guard_name' => 'web',
        ]);
        $admin->assignRole($visibleRole);
        $menuKey = AdminMenuRegistry::resourceKey(DonationResource::class);

        AdminMenuRoleVisibility::query()->insert([
            ['role_id' => $hiddenRole->id, 'menu_key' => $menuKey, 'is_visible' => false],
            ['role_id' => $visibleRole->id, 'menu_key' => $menuKey, 'is_visible' => true],
        ]);

        $this->actingAs($admin);

        $this->assertFalse(DonationResource::shouldRegisterNavigation());
    }

    public function test_admin_menu_settings_save_super_admin_visibility_and_refresh_the_sidebar(): void
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole($role);
        $menuKey = AdminMenuRegistry::resourceKey(DonationResource::class);
        $hash = sha1($menuKey);

        Livewire::actingAs($admin)
            ->test(AdminMenuSettings::class)
            ->assertSet('roles', fn (array $roles): bool => collect($roles)->contains('id', $role->id))
            ->assertSee('Role-Based Admin Menu Visibility')
            ->assertSee('Donation')
            ->assertSee('Super Admin')
            ->assertSeeHtml('wire:submit.prevent="save"')
            ->assertDontSeeHtml('wire:model.defer=')
            ->assertSeeHtml('type="submit"')
            ->assertSee('Save menu visibility')
            ->assertDontSee('Create at least one web admin role before configuring menu visibility.')
            ->assertDontSee('No admin menu items were found.')
            ->set("visibility.{$role->id}.{$hash}", false)
            ->call('save')
            ->assertDispatched('refresh-sidebar');

        $this->assertDatabaseHas('admin_menu_role_visibilities', [
            'role_id' => $role->id,
            'menu_key' => $menuKey,
            'is_visible' => false,
        ]);
        $this->assertFalse(DonationResource::shouldRegisterNavigation());
    }

    public function test_menu_matrix_excludes_entries_that_cannot_register_sidebar_navigation(): void
    {
        $keys = collect(AdminMenuRegistry::items())->pluck('key')->all();

        $this->assertNotContains(AdminMenuRegistry::pageKey(CloudBackups::class), $keys);
        $this->assertNotContains(AdminMenuRegistry::resourceKey(AccommodationUnitResource::class), $keys);
        $this->assertNotContains(AdminMenuRegistry::resourceKey(AccommodationBookingResource::class), $keys);
        $this->assertNotContains(AdminMenuRegistry::resourceKey(AccommodationPaymentResource::class), $keys);
        $this->assertContains(AdminMenuRegistry::pageKey(CronJobs::class), $keys);
        $this->assertContains(AdminMenuRegistry::pageKey(GoshenReferralSettings::class), $keys);
        $this->assertContains(AdminMenuRegistry::resourceKey(DonationResource::class), $keys);
    }

    public function test_settings_quick_links_hide_pages_without_permission(): void
    {
        [$admin] = $this->adminWithRolePermission(
            AdminPermissions::resourcePermission(AppSettingResource::class),
        );

        $this->actingAs($admin);

        $labels = $this->settingsQuickLinkLabels();

        $this->assertNotContains('Payment Gateways', $labels);
        $this->assertNotContains('Cloud Backups', $labels);
        $this->assertNotContains('Role Permissions', $labels);
    }

    public function test_settings_quick_links_show_pages_with_permission(): void
    {
        [$admin] = $this->adminWithRolePermissions([
            AdminPermissions::resourcePermission(AppSettingResource::class),
            AdminPermissions::PAYMENT_GATEWAYS,
            AdminPermissions::CLOUD_BACKUPS,
            AdminPermissions::resourcePermission(RoleResource::class),
        ]);

        $this->actingAs($admin);

        $labels = $this->settingsQuickLinkLabels();

        $this->assertContains('Payment Gateways', $labels);
        $this->assertContains('Cloud Backups', $labels);
        $this->assertContains('Role Permissions', $labels);
    }

    public function test_settings_quick_links_honor_admin_menu_visibility(): void
    {
        [$admin, $role] = $this->adminWithRolePermissions([
            AdminPermissions::resourcePermission(AppSettingResource::class),
            AdminPermissions::PAYMENT_GATEWAYS,
            AdminPermissions::CLOUD_BACKUPS,
        ]);

        AdminMenuRoleVisibility::query()->create([
            'role_id' => $role->id,
            'menu_key' => AdminMenuRegistry::pageKey(CloudBackups::class),
            'is_visible' => false,
        ]);

        $this->actingAs($admin);

        $labels = $this->settingsQuickLinkLabels();

        $this->assertContains('Payment Gateways', $labels);
        $this->assertNotContains('Cloud Backups', $labels);
    }

    public function test_goshen_console_cards_honor_admin_menu_visibility(): void
    {
        [$admin, $role] = $this->adminWithRolePermission(
            AdminPermissions::resourcePermission(GoshenBookingResource::class),
        );

        $this->actingAs($admin);

        $this->assertContains('Bookings', $this->goshenConsoleCardTitles());

        AdminMenuRoleVisibility::query()->create([
            'role_id' => $role->id,
            'menu_key' => AdminMenuRegistry::resourceKey(GoshenBookingResource::class),
            'is_visible' => false,
        ]);

        $this->assertNotContains('Bookings', $this->goshenConsoleCardTitles());
    }

    public function test_non_super_admin_cannot_see_or_assign_super_admin(): void
    {
        $superRole = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        $managerRole = Role::query()->firstOrCreate([
            'name' => 'admin_manager',
            'guard_name' => 'web',
        ]);

        foreach ([UserResource::class, RoleResource::class] as $resource) {
            $permission = Permission::query()->firstOrCreate([
                'name' => AdminPermissions::resourcePermission($resource),
                'guard_name' => 'web',
            ]);
            $managerRole->givePermissionTo($permission);
        }

        $manager = User::factory()->create(['email' => 'manager@example.test']);
        $manager->assignRole($managerRole);
        $superAdmin = User::factory()->create(['email' => 'super@example.test']);
        $superAdmin->assignRole($superRole);

        $this->actingAs($manager);

        $this->assertFalse(UserResource::getEloquentQuery()->whereKey($superAdmin->id)->exists());
        $this->assertFalse(RoleResource::getEloquentQuery()->whereKey($superRole->id)->exists());
        $this->assertFalse(UserResource::canEdit($superAdmin));
        $this->assertFalse(RoleResource::canEdit($superRole));

        Livewire::actingAs($manager)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Crafted Admin',
                'email' => 'crafted@example.test',
                'password' => 'password',
                'roles' => [$superRole->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $mobileSuperRole = Role::query()->create([
            'name' => 'super_admin',
            'guard_name' => 'mobile',
        ]);

        Livewire::actingAs($manager)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Crafted Mobile Admin',
                'email' => 'crafted-mobile@example.test',
                'password' => 'password',
                'roles' => [$mobileSuperRole->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $craftedUser = User::factory()->create();
        $craftedUser->roles()->attach($mobileSuperRole->id);
        $this->actingAs($craftedUser);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(app(\Sunny\Fundraising\Services\DefaultPermissionResolver::class)->canManage($craftedUser));
    }

    /**
     * @return array{0: User, 1: Role}
     */
    private function adminWithRolePermission(string $permissionName): array
    {
        return $this->adminWithRolePermissions([$permissionName]);
    }

    /**
     * @param array<int, string> $permissionNames
     * @return array{0: User, 1: Role}
     */
    private function adminWithRolePermissions(array $permissionNames): array
    {
        $permissions = collect($permissionNames)
            ->map(fn (string $permissionName): Permission => Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]));
        $role = Role::query()->firstOrCreate([
            'name' => 'role_'.sha1(implode('|', $permissionNames)),
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permissions);

        $admin = User::factory()->create();
        $admin->assignRole($role);

        return [$admin, $role];
    }

    /**
     * @return array<int, string>
     */
    private function settingsQuickLinkLabels(): array
    {
        return collect((new AppSettings)->getViewData()['quickLinks'])
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function goshenConsoleCardTitles(): array
    {
        return collect((new GoshenRetreatConsole)->getViewData()['cards'])
            ->pluck('title')
            ->values()
            ->all();
    }
}
