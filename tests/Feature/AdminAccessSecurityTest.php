<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Services\AdminAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $super = false): User
    {
        $user = User::factory()->create();
        if ($super) {
            $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        } else {
            $user->assignRole(Role::findOrCreate('manager', 'web'));
            $user->givePermissionTo(Permission::findOrCreate('manage_user', 'web'));
            $user->givePermissionTo(Permission::findOrCreate('manage_role', 'web'));
        }

        return $user;
    }

    public function test_manager_can_edit_profile_but_cannot_submit_individual_grants(): void
    {
        $manager = $this->admin();
        $extra = Permission::findOrCreate('manage_app_setting', 'web');
        Livewire::actingAs($manager)->test(EditUser::class, ['record' => $manager->id])
            ->fillForm(['name' => 'Updated manager'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated manager', $manager->fresh()->name);

        Livewire::actingAs($manager)->test(EditUser::class, ['record' => $manager->id])
            ->set('data.permissions', [$extra->id])->call('save')->assertHasFormErrors(['permissions']);
        $this->assertFalse($manager->fresh()->can('manage_app_setting'));
    }

    public function test_manager_cannot_assign_a_role_or_edit_role_permissions(): void
    {
        $manager = $this->admin();
        $role = Role::findOrCreate('super_admin', 'web');
        Livewire::actingAs($manager)->test(EditUser::class, ['record' => $manager->id])
            ->set('data.roles', [$role->id])->call('save')->assertHasFormErrors(['roles']);
        $this->assertFalse($manager->fresh()->hasRole($role));
        $ordinary = Role::findOrCreate('ordinary', 'web');
        Livewire::actingAs($manager)->test(EditRole::class, ['record' => $ordinary->id])->assertForbidden();
    }

    public function test_admin_actions_follow_server_side_protections(): void
    {
        $manager = $this->admin();
        Livewire::actingAs($manager)->test(EditUser::class, ['record' => $manager->id])
            ->assertActionHidden('delete');
        Livewire::actingAs($manager)->test(\App\Filament\Resources\UserResource\Pages\ListUsers::class)
            ->assertActionHidden('create');
        Livewire::actingAs($manager)->test(\App\Filament\Resources\RoleResource\Pages\ListRoles::class)
            ->assertActionHidden('create')
            ->assertActionHidden(\Filament\Actions\Testing\TestAction::make('edit')->table($manager->roles->first()))
            ->assertActionHidden(\Filament\Actions\Testing\TestAction::make('delete')->table($manager->roles->first()));
        $stronger = $this->admin();
        $stronger->givePermissionTo(Permission::findOrCreate('manage_payment_gateways', 'web'));
        Livewire::actingAs($manager)->test(\App\Filament\Resources\UserResource\Pages\ListUsers::class)
            ->assertActionHidden(\Filament\Actions\Testing\TestAction::make('edit')->table($stronger));
        $super = $this->admin(true);
        Livewire::actingAs($super)->test(EditUser::class, ['record' => $super->id])
            ->assertActionHidden('delete');
        Livewire::actingAs($super)->test(EditUser::class, ['record' => $manager->id])
            ->assertActionVisible('delete');
        Livewire::actingAs($super)->test(EditRole::class, ['record' => $super->roles->first()->id])
            ->assertActionHidden('delete');
    }

    public function test_manager_cannot_reset_a_stronger_accounts_credentials(): void
    {
        $manager = $this->admin();
        $stronger = $this->admin();
        $stronger->givePermissionTo(Permission::findOrCreate('manage_payment_gateways', 'web'));
        Livewire::actingAs($manager)->test(EditUser::class, ['record' => $stronger->id])->assertForbidden();
    }

    public function test_super_admin_grants_and_revokes_permissions_with_audit_and_legacy_preservation(): void
    {
        $super = $this->admin(true);
        $target = $this->admin();
        $extra = Permission::findOrCreate('manage_app_setting', 'web');
        $legacy = Permission::findOrCreate('legacy_external_access', 'web');
        $target->givePermissionTo($legacy);
        Livewire::actingAs($super)->test(EditUser::class, ['record' => $target->id])
            ->fillForm(['permissions' => [$extra->id]])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($target->fresh()->can($extra->name));
        $this->assertTrue($target->fresh()->can($legacy->name));
        Livewire::actingAs($super)->test(EditUser::class, ['record' => $target->id])
            ->fillForm(['permissions' => []])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($target->fresh()->can($extra->name));
        $this->assertDatabaseCount('admin_access_audits', 2);
        $this->assertStringNotContainsString('password', DB::table('admin_access_audits')->first()->after);
    }

    public function test_last_super_admin_cannot_be_demoted_or_deleted(): void
    {
        $super = $this->admin(true);
        $this->actingAs($super);
        $this->assertFalse(AdminAccessService::canDeleteUser($super));
        try {
            app(AdminAccessService::class)->saveUser($super, ['name' => 'Should roll back'], ['roles' => []]);
            $this->fail('Expected last Super Admin protection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('roles', $e->errors());
        }
        $this->assertTrue($super->fresh()->hasRole('super_admin', 'web'));
        $this->assertNotSame('Should roll back', $super->fresh()->name);
        $this->admin(true);
        app(AdminAccessService::class)->saveUser($super, [], ['roles' => []]);
        $this->assertFalse($super->fresh()->hasRole('super_admin', 'web'));
    }

    public function test_role_guard_and_reserved_name_changes_are_rejected_atomically(): void
    {
        $super = $this->admin(true);
        $this->actingAs($super);
        $role = Role::findOrCreate('ordinary', 'web');
        $permission = Permission::findOrCreate('manage_user', 'web');
        $role->givePermissionTo($permission);
        foreach ([['guard_name' => 'mobile'], ['name' => 'super_admin']] as $change) {
            try {
                app(AdminAccessService::class)->saveRole($role, $change, []);
                $this->fail('Expected immutable role identity protection.');
            } catch (ValidationException) {
                $this->assertSame('web', $role->fresh()->guard_name);
                $this->assertSame('ordinary', $role->fresh()->name);
                $this->assertTrue($role->fresh()->hasPermissionTo($permission));
            }
        }
    }

    public function test_invalid_cross_guard_and_malformed_permission_ids_do_not_mutate_user(): void
    {
        $super = $this->admin(true);
        $this->actingAs($super);
        $target = $this->admin();
        $mobile = Permission::findOrCreate('mobile_only', 'mobile');
        foreach ([[$mobile->id], [[1]], ['bad']] as $ids) {
            try {
                app(AdminAccessService::class)->saveUser($target, ['name' => 'Must not save'], ['permissions' => $ids]);
                $this->fail('Expected invalid permission rejection.');
            } catch (ValidationException) {
                $this->assertNotSame('Must not save', $target->fresh()->name);
                $this->assertTrue($target->fresh()->can('manage_user'));
            }
        }
    }

    public function test_accommodation_export_requires_booking_permission_for_both_routes(): void
    {
        $user = User::factory()->create();
        $category = \App\Models\AccommodationCategory::create(['name' => 'Test room', 'slug' => 'test-room']);
        $booking = \App\Models\AccommodationBooking::create([
            'booking_reference' => 'LOCAL-TEST-1',
            'user_id' => \App\Models\MobileUser::where('email', $user->email)->firstOrFail()->id,
            'accommodation_category_id' => $category->id,
            'check_in_date' => '2026-10-10', 'checkout_date' => '2026-10-11', 'nights' => 1,
        ]);
        $this->actingAs($user)->get('/api/admin/accommodation-bookings/export-csv')->assertForbidden();
        $this->get('/admin/accommodation-bookings/export-csv')->assertForbidden();
        $this->get('/admin/accommodation-bookings/export/csv')->assertForbidden();
        $this->get('/admin/accommodation-bookings/'.$booking->id.'/receipt')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('manage_accommodation_booking', 'web'));
        $this->actingAs($user->fresh())->get('/api/admin/accommodation-bookings/export-csv')->assertOk();
        $this->get('/admin/accommodation-bookings/export/csv')->assertOk();
        $this->get('/admin/accommodation-bookings/'.$booking->id.'/receipt')->assertOk();
    }

    public function test_sanctum_export_denies_a_token_without_booking_permission(): void
    {
        $user = User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->getJson('/api/admin/accommodation-bookings/export-csv')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('manage_accommodation_booking', 'web'));
        \Laravel\Sanctum\Sanctum::actingAs($user->fresh());
        $this->getJson('/api/admin/accommodation-bookings/export-csv')->assertOk();
    }

    public function test_role_form_rejects_guard_tampering_and_revokes_other_users_access(): void
    {
        $super = $this->admin(true);
        $role = Role::findOrCreate('settings_operator', 'web');
        $permission = Permission::findOrCreate('manage_payment_gateways', 'web');
        $role->givePermissionTo($permission);
        $target = User::factory()->create();
        $target->assignRole($role);
        Livewire::actingAs($super)->test(EditRole::class, ['record' => $role->id])
            ->set('data.guard_name', 'mobile')->call('save')->assertHasFormErrors(['guard_name']);
        $this->assertSame('web', $role->fresh()->guard_name);
        $this->assertTrue($target->fresh()->can($permission->name));
        Livewire::actingAs($super)->test(EditRole::class, ['record' => $role->id])
            ->fillForm(['permissions' => []])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($target->fresh()->can($permission->name));
    }
}
