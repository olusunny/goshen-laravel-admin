<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AddonCapabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_require_mobile_authentication(): void
    {
        $this->getJson('/api/v1/mobile/capabilities')->assertUnauthorized();
    }

    public function test_only_active_addon_capabilities_are_exposed_with_user_scoped_permissions(): void
    {
        $member = $this->mobileUser();
        Permission::findOrCreate('attendance.scan', 'mobile');
        $member->givePermissionTo('attendance.scan');

        $this->addon('church-tools.prayer-attendance', Addon::STATUS_ACTIVE, [
            'prayer_session_attendance' => [
                'permissions' => ['attendance.scan', 'attendance.coordinate'],
            ],
        ]);
        $this->addon('church-tools.inactive-feature', Addon::STATUS_INACTIVE, [
            'inactive_feature' => [
                'permissions' => ['attendance.scan'],
            ],
        ]);

        $this->withToken($member->issueApiToken())
            ->getJson('/api/v1/mobile/capabilities')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.capabilities.0.key', 'prayer_session_attendance')
            ->assertJsonPath('data.capabilities.0.permissions', ['attendance.scan'])
            ->assertJsonCount(1, 'data.capabilities');
    }

    public function test_active_capability_is_discoverable_without_an_operational_permission(): void
    {
        $member = $this->mobileUser();
        $this->addon('church-tools.prayer-attendance', Addon::STATUS_ACTIVE, [
            'prayer_session_attendance' => [
                'permissions' => ['attendance.scan'],
            ],
        ]);

        $this->withToken($member->issueApiToken())
            ->getJson('/api/v1/mobile/capabilities')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.capabilities.0.key', 'prayer_session_attendance')
            ->assertJsonPath('data.capabilities.0.permissions', []);
    }

    public function test_unverified_mobile_users_cannot_discover_capabilities(): void
    {
        $member = $this->mobileUser(['is_verified' => false]);

        $this->withToken($member->issueApiToken())
            ->getJson('/api/v1/mobile/capabilities')
            ->assertForbidden();
    }

    public function test_super_admin_receives_addon_control_permissions(): void
    {
        $member = $this->mobileUser();
        Role::findOrCreate('super_admin', 'mobile');
        $member->assignRole('super_admin');
        $this->addon('church-tools.prayer-attendance', Addon::STATUS_ACTIVE, [
            'prayer_session_attendance' => [
                'permissions' => ['prayer_session_attendance.confirm'],
            ],
        ]);

        $this->withToken($member->issueApiToken())
            ->getJson('/api/v1/mobile/capabilities')
            ->assertOk()
            ->assertJsonPath('data.capabilities.0.permissions', ['prayer_session_attendance.confirm']);
    }

    /**
     * @param array<string, array{permissions: array<int, string>}> $capabilities
     */
    private function addon(string $packageKey, string $status, array $capabilities): Addon
    {
        return Addon::query()->create([
            'package_key' => $packageKey,
            'name' => 'Test add-on',
            'installed_version' => '1.0.0',
            'status' => $status,
            'manifest' => [
                'capabilities' => $capabilities,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function mobileUser(array $attributes = []): MobileUser
    {
        return MobileUser::query()->create(array_merge([
            'name' => 'Capability Member',
            'email' => 'capability-'.uniqid().'@example.test',
            'password' => 'password',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
        ], $attributes));
    }
}
