<?php

namespace Tests\Feature;

use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlHubMobileUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_list_and_delete_mobile_users(): void
    {
        $manager = $this->manager();
        $token = $manager->issueApiToken();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/mobile-users', [
                'data' => array_merge($this->profilePayload('created@example.test'), [
                    'api_token' => $token,
                ]),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.user.title', 'Mrs.')
            ->assertJsonPath('data.user.marital_status', 'Married');

        $userId = $create->json('data.user.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/control-hub/mobile-users/{$userId}", [
                'data' => array_merge($this->profilePayload('updated@example.test'), [
                    'api_token' => $token,
                    'title' => 'Miss',
                    'marital_status' => 'Single',
                    'first_name' => 'Updated',
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'updated@example.test')
            ->assertJsonPath('data.user.title', 'Miss')
            ->assertJsonPath('data.user.marital_status', 'Single');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/mobile-users/search', [
                'data' => [
                    'api_token' => $token,
                    'query' => 'updated@example.test',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.users.0.email', 'updated@example.test');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/mobile-users', [
                'data' => [
                    'api_token' => $token,
                    'email' => $manager->email,
                    'query' => 'updated@example.test',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.users.0.email', 'updated@example.test');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/control-hub/mobile-users/{$userId}/delete", [
                'data' => ['api_token' => $token],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.is_deleted', true);

        $this->assertTrue((bool) MobileUser::query()->findOrFail($userId)->is_deleted);
    }

    public function test_regular_member_cannot_manage_mobile_users(): void
    {
        $member = MobileUser::query()->create([
            'name' => 'Regular Member',
            'title' => 'Mr.',
            'email' => 'regular@example.test',
            'phone' => '+447700900111',
            'password' => 'secret',
            'gender' => 'male',
            'marital_status' => 'Single',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $token = $member->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/mobile-users', [
                'data' => array_merge($this->profilePayload('blocked@example.test'), [
                    'api_token' => $token,
                ]),
            ])
            ->assertForbidden();
    }

    public function test_manager_must_set_password_when_creating_mobile_user(): void
    {
        $manager = $this->manager();
        $token = $manager->issueApiToken();
        $payload = $this->profilePayload('no-password@example.test');
        unset($payload['password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/mobile-users', [
                'data' => array_merge($payload, [
                    'api_token' => $token,
                ]),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'The password field is required.');

        $this->assertDatabaseMissing('mobile_users', [
            'email' => 'no-password@example.test',
        ]);
    }

    private function manager(): MobileUser
    {
        foreach (['manage_mobile_users', 'create_mobile_users', 'update_mobile_users', 'delete_mobile_users'] as $permission) {
            Permission::findOrCreate($permission, 'mobile');
        }

        $role = Role::findOrCreate('event_manager', 'mobile');
        $role->givePermissionTo(['manage_mobile_users', 'create_mobile_users', 'update_mobile_users', 'delete_mobile_users']);

        $manager = MobileUser::query()->create([
            'name' => 'User Manager',
            'title' => 'Mr.',
            'email' => 'manager@example.test',
            'phone' => '+447700900000',
            'password' => 'secret',
            'gender' => 'male',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $manager->assignRole($role);

        return $manager;
    }

    private function profilePayload(string $email): array
    {
        return [
            'title' => 'Mrs.',
            'first_name' => 'Control',
            'middle_name' => 'Hub',
            'last_name' => 'Member',
            'email' => $email,
            'phone' => '+447700900222',
            'gender' => 'female',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'country_of_residence' => 'United Kingdom',
            'state_county_province' => 'London',
            'address' => '1 Church Road, London',
            'password' => 'password123',
            'is_verified' => true,
        ];
    }
}
