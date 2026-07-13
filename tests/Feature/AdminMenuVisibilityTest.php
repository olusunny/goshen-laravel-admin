<?php

namespace Tests\Feature;

use App\Filament\Pages\AppSettings;
use App\Filament\Pages\CloudBackups;
use App\Filament\Pages\CronJobs;
use App\Filament\Pages\GoshenRetreatConsole;
use App\Filament\Pages\PaymentGateways;
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
     *
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
        return collect((new AppSettings())->getViewData()['quickLinks'])
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function goshenConsoleCardTitles(): array
    {
        return collect((new GoshenRetreatConsole())->getViewData()['cards'])
            ->pluck('title')
            ->values()
            ->all();
    }
}
