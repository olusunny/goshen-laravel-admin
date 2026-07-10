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

        $mobile = MobileUser::query()
            ->whereRaw('LOWER(email) = ?', ['shared@example.test'])
            ->firstOrFail();

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

        $mobile = MobileUser::query()
            ->whereRaw('LOWER(email) = ?', ['shared-admin@example.test'])
            ->firstOrFail();

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
