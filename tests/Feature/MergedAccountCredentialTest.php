<?php

namespace Tests\Feature;

use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MergedAccountCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_accepts_matching_admin_password_and_merges_credentials(): void
    {
        $admin = User::query()->create([
            'name' => 'Shared Admin',
            'email' => 'shared@example.test',
            'password' => 'AdminPassw0rd!',
        ]);

        $mobile = MobileUser::query()->create([
            'name' => 'Shared Mobile',
            'email' => 'shared@example.test',
            'phone' => '+447700900001',
            'password' => 'OldMobilePassw0rd!',
            'gender' => 'female',
            'member_type' => 'church_member',
            'is_verified' => false,
            'email_verified_at' => null,
        ]);

        $admin->forceFill(['password' => Hash::make('AdminPassw0rd!')])->saveQuietly();
        $mobile->forceFill([
            'password' => Hash::make('OldMobilePassw0rd!'),
            'is_verified' => false,
            'email_verified_at' => null,
        ])->saveQuietly();

        $this->postJson('/loginUser', [
            'data' => [
                'email' => 'shared@example.test',
                'password' => 'AdminPassw0rd!',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Signed in successfully.');

        $mobile->refresh();

        $this->assertTrue(Hash::check('AdminPassw0rd!', $mobile->password));
        $this->assertTrue((bool) $mobile->is_verified);
        $this->assertNotNull($mobile->email_verified_at);
    }

    public function test_admin_login_accepts_matching_mobile_password_and_merges_credentials(): void
    {
        $admin = User::query()->create([
            'name' => 'Shared Admin',
            'email' => 'shared-admin@example.test',
            'password' => 'OldAdminPassw0rd!',
        ]);

        $mobile = MobileUser::query()->create([
            'name' => 'Shared Mobile',
            'email' => 'shared-admin@example.test',
            'phone' => '+447700900002',
            'password' => 'MobilePassw0rd!',
            'gender' => 'female',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $admin->forceFill(['password' => Hash::make('OldAdminPassw0rd!')])->saveQuietly();
        $mobile->forceFill(['password' => Hash::make('MobilePassw0rd!')])->saveQuietly();

        $this->assertFalse(Hash::check('MobilePassw0rd!', $admin->password));
        $this->assertTrue(Auth::attempt([
            'email' => 'shared-admin@example.test',
            'password' => 'MobilePassw0rd!',
        ]));

        $this->assertTrue(Hash::check('MobilePassw0rd!', $admin->refresh()->password));
    }
}
