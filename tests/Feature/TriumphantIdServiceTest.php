<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\TriumphantIdService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TriumphantIdServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensuring_reserved_roles_does_not_overwrite_it_manager_permissions(): void
    {
        $service = app(TriumphantIdService::class);
        $service->ensureRoles();

        $allowed = Permission::findOrCreate('manage_goshen_ticket', 'web');
        $forbidden = Permission::findOrCreate('manage_app_setting', 'web');
        $role = Role::query()
            ->where('guard_name', 'web')
            ->where('name', TriumphantIdService::IT_MANAGER_ROLE)
            ->firstOrFail();
        $role->syncPermissions([$allowed]);

        $service->ensureRoles();

        $this->assertTrue($role->refresh()->hasPermissionTo($allowed));
        $this->assertFalse($role->hasPermissionTo($forbidden));
    }

    public function test_mobile_users_receive_formatted_triumphant_ids_and_reuse_deleted_general_ids(): void
    {
        $service = app(TriumphantIdService::class);
        $service->ensureRoles();

        $mainPastor = $this->mobileUser('main-pastor@example.test', 'Main Pastor');
        $mainPastor->assignRole(TriumphantIdService::MAIN_PASTOR_ROLE);
        $service->assignFor($mainPastor);

        $itManager = $this->mobileUser('it-manager@example.test', 'IT Manager');
        $itManager->assignRole(TriumphantIdService::IT_MANAGER_ROLE);
        $service->assignFor($itManager);

        $member = $this->mobileUser('member@example.test', 'Member One');

        $this->assertSame('T001', $mainPastor->refresh()->triumphant_id);
        $this->assertSame(1, (int) $mainPastor->triumphant_id_sequence);
        $this->assertSame('T002', $itManager->refresh()->triumphant_id);
        $this->assertSame(2, (int) $itManager->triumphant_id_sequence);
        $this->assertSame('T003', $member->refresh()->triumphant_id);
        $this->assertSame(3, (int) $member->triumphant_id_sequence);

        $member->forceFill(['is_deleted' => true])->save();

        $this->assertNull($member->refresh()->triumphant_id);
        $this->assertNull($member->triumphant_id_sequence);

        $replacement = $this->mobileUser('replacement@example.test', 'Replacement Member');

        $this->assertSame('T003', $replacement->refresh()->triumphant_id);
        $this->assertSame(3, (int) $replacement->triumphant_id_sequence);
    }

    public function test_it_manager_web_role_can_be_assigned_to_multiple_admin_users(): void
    {
        $service = app(TriumphantIdService::class);
        $service->ensureRoles();
        $role = Role::findByName(TriumphantIdService::IT_MANAGER_ROLE, 'web');
        $first = $this->adminUser('first-it-manager@example.test', 'First IT Manager');
        $second = $this->adminUser('second-it-manager@example.test', 'Second IT Manager');

        $first->assignRole($role);
        $service->assertReservedWebRolesAvailable([$role->id], $second);
        $second->syncRoles([$role]);

        $this->assertTrue($first->fresh()->hasRole($role));
        $this->assertTrue($second->fresh()->hasRole($role));
    }

    public function test_super_admin_can_create_and_edit_additional_it_manager_admins(): void
    {
        $service = app(TriumphantIdService::class);
        $service->ensureRoles();
        $role = Role::findByName(TriumphantIdService::IT_MANAGER_ROLE, 'web');
        $superAdmin = $this->adminUser('super-admin@example.test', 'Super Admin');
        $superAdmin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $existing = $this->adminUser('existing-it-manager@example.test', 'Existing IT Manager');
        $existing->assignRole($role);

        Livewire::actingAs($superAdmin)->test(CreateUser::class)
            ->fillForm([
                'name' => 'Additional IT Manager',
                'email' => 'additional-it-manager@example.test',
                'password' => 'test-password',
                'roles' => [$role->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'additional-it-manager@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole($role));

        $another = $this->adminUser('another-admin@example.test', 'Another Admin');
        Livewire::actingAs($superAdmin)->test(EditUser::class, ['record' => $another->id])
            ->fillForm(['roles' => [$role->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($another->fresh()->hasRole($role));
        $this->assertTrue($existing->fresh()->hasRole($role));
    }

    public function test_it_manager_mobile_role_still_preserves_its_unique_member_id(): void
    {
        $service = app(TriumphantIdService::class);
        $service->ensureRoles();
        $role = Role::findByName(TriumphantIdService::IT_MANAGER_ROLE, 'mobile');
        $holder = $this->mobileUser('mobile-it-manager@example.test', 'Mobile IT Manager');
        $holder->assignRole($role);
        $service->assignFor($holder);
        $contender = $this->mobileUser('other-mobile-it-manager@example.test', 'Other Mobile IT Manager');

        $this->assertValidationExceptionContains(
            fn () => $contender->assignRole($role),
            'Only one user can hold this role.',
        );
        $this->assertSame('T002', $holder->fresh()->triumphant_id);
        $this->assertFalse($contender->fresh()->hasRole($role));
    }

    public function test_main_pastor_role_is_limited_to_one_active_mobile_and_web_holder(): void
    {
        $service = app(TriumphantIdService::class);
        $service->ensureRoles();

        $mobileRole = Role::query()
            ->where('guard_name', 'mobile')
            ->where('name', TriumphantIdService::MAIN_PASTOR_ROLE)
            ->firstOrFail();
        $mobileHolder = $this->mobileUser('mobile-holder@example.test', 'Mobile Holder');
        $mobileHolder->assignRole($mobileRole);
        $service->assignFor($mobileHolder);

        $mobileContender = $this->mobileUser('mobile-contender@example.test', 'Mobile Contender');

        $this->assertValidationExceptionContains(
            fn () => $service->assertReservedMobileRolesAvailable([$mobileRole->id], $mobileContender),
            'Only one user can hold this role.',
        );

        $webRole = Role::query()
            ->where('guard_name', 'web')
            ->where('name', TriumphantIdService::MAIN_PASTOR_ROLE)
            ->firstOrFail();
        $adminHolder = $this->adminUser('admin-holder@example.test', 'Admin Holder');
        $adminHolder->assignRole($webRole);

        $adminContender = $this->adminUser('admin-contender@example.test', 'Admin Contender');

        $this->assertValidationExceptionContains(
            fn () => $adminContender->assignRole($webRole),
            'Only one admin user can hold this role.',
        );
    }

    public function test_visitors_do_not_display_ids_and_keep_their_sequence_reserved_until_deletion(): void
    {
        Carbon::setTestNow('2026-07-21 16:00:00');

        try {
            $visitor = MobileUser::query()->create([
                'name' => 'Guest Visitor',
                'email' => 'guest-visitor@example.test',
                'phone' => '+2348012345678',
                'password' => 'secret',
                'gender' => 'female',
                'member_type' => 'visitor',
                'is_verified' => true,
                'email_verified_at' => now(),
            ]);

            $this->assertNull($visitor->refresh()->triumphant_id);
            $this->assertNull($visitor->triumphant_id_sequence);

            $member = $this->mobileUser('status-change@example.test', 'Status Change');
            $this->assertSame('T003', $member->refresh()->triumphant_id);
            $this->assertSame(3, (int) $member->triumphant_id_sequence);

            $member->forceFill(['member_type' => 'visitor'])->save();

            $member = $member->refresh();
            $this->assertNull($member->triumphant_id);
            $this->assertSame(3, (int) $member->triumphant_id_sequence);
            $this->assertSame('2026-07-21 16:00:00', $member->membership_status_changed_at?->toDateTimeString());

            $replacement = $this->mobileUser('replacement-after-status@example.test', 'Replacement After Status');
            $this->assertSame('T004', $replacement->refresh()->triumphant_id);

            $this->assertValidationExceptionContains(
                fn () => $member->forceFill(['member_type' => 'church_member'])->save(),
                'You can change it again on',
            );

            Carbon::setTestNow(now()->addDays(30)->addSecond());
            $member->refresh()->forceFill(['member_type' => 'church_member'])->save();

            $this->assertSame('T003', $member->refresh()->triumphant_id);
            $this->assertSame(3, (int) $member->triumphant_id_sequence);

            $member->forceFill(['is_deleted' => true])->save();
            $this->assertNull($member->refresh()->triumphant_id);
            $this->assertNull($member->triumphant_id_sequence);

            $replacementAfterDeletion = $this->mobileUser('replacement-after-deletion@example.test', 'Replacement After Deletion');
            $this->assertSame('T003', $replacementAfterDeletion->refresh()->triumphant_id);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function mobileUser(string $email, string $name): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '+23480'.random_int(10000000, 99999999),
            'password' => 'secret',
            'gender' => 'male',
            'member_type' => 'church_member',
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Mercy Road, Lagos',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function adminUser(string $email, string $name): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret',
        ]);
    }

    private function assertValidationExceptionContains(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())->flatten()->implode(' ');

            $this->assertStringContainsString($expectedMessage, $messages);

            return;
        }

        $this->fail('Expected a validation exception for a duplicate reserved role assignment.');
    }
}
