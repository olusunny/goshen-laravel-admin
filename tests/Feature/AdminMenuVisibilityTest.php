<?php

namespace Tests\Feature;

use App\Filament\Pages\PaymentGateways;
use App\Filament\Resources\DonationResource;
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
        [$admin, $role] = $this->adminWithRolePermission(AdminPermissions::PAYMENT_GATEWAYS);

        $this->actingAs($admin);

        $this->assertTrue(PaymentGateways::canAccess());
        $this->assertTrue(PaymentGateways::shouldRegisterNavigation());

        AdminMenuRoleVisibility::query()->create([
            'role_id' => $role->id,
            'menu_key' => AdminMenuRegistry::pageKey(PaymentGateways::class),
            'is_visible' => false,
        ]);

        $this->assertTrue(PaymentGateways::canAccess());
        $this->assertFalse(PaymentGateways::shouldRegisterNavigation());
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
        $permission = Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
        $role = Role::query()->firstOrCreate([
            'name' => 'role_'.str($permissionName)->replace('*', 'all')->replace('.', '_')->slug('_'),
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $admin = User::factory()->create();
        $admin->assignRole($role);

        return [$admin, $role];
    }
}
