<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CompatibilityController;
use App\Models\{AppSetting, MobileUser, User};
use App\Services\DynamicSmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Lcobucci\JWT\Token\{DataSet, Plain, Signature};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class IdentitySecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(DynamicSmtpMailer::class)->shouldReceive('sendRaw')->andReturnNull();
    }

    private function member(string $email = 'member@example.test', array $attributes = []): MobileUser
    {
        return MobileUser::create(array_merge([
            'name' => 'Original Member', 'email' => $email, 'password' => 'OriginalPassword123!',
            'member_type' => 'visitor', 'is_verified' => true, 'email_verified_at' => now(),
        ], $attributes));
    }

    private function signup(string $email): array
    {
        return ['email' => $email, 'name' => 'Attacker Name', 'first_name' => 'Attacker',
            'last_name' => 'Name', 'phone' => '+2348000000001', 'gender' => 'Male',
            'member_type' => 'visitor', 'password' => 'AttackerPassword123!'];
    }

    private function firebase(string $uid, string $phone): void
    {
        AppSetting::updateOrCreate(['key' => 'mobile_phone_otp_login_enabled'], ['value' => '1']);
        $token = new Plain(new DataSet([], ''), new DataSet([
            'sub' => $uid, 'phone_number' => $phone, 'firebase' => ['sign_in_provider' => 'phone'],
        ], ''), new Signature('', ''));
        $this->mock(FirebaseAuth::class)->shouldReceive('verifyIdToken')->with('firebase-proof', true, 60)->andReturn($token);
    }

    public function test_registration_preserves_existing_accounts_with_any_verification_state(): void
    {
        foreach ([[false, null], [true, null], [false, now()], [true, now()]] as $index => [$verified, $at]) {
            $member = $this->member("existing{$index}@example.test", ['is_verified' => $verified, 'email_verified_at' => $at]);
            $member->issueApiToken();
            $before = $member->fresh()->getAttributes();
            foreach (['/registerUser', '/api/registerUser'] as $route) {
                $this->postJson($route, ['data' => $this->signup(strtoupper($member->email))])
                    ->assertOk()->assertJsonPath('status', 'error');
                $this->assertSame($before, $member->fresh()->getAttributes());
            }
            $this->assertSame(1, MobileUser::where('email', $member->email)->count());
            $this->assertSame(1, $member->wallet()->count());
        }
    }

    public function test_registration_cannot_overwrite_linked_admin_with_missing_verification_marker(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $member = MobileUser::where('email', $admin->email)->firstOrFail();
        $member->forceFill(['email_verified_at' => null])->saveQuietly();
        $before = $member->fresh()->getAttributes();
        $adminPassword = $admin->fresh()->password;
        $this->postJson('/registerUser', ['data' => $this->signup($member->email)])->assertJsonPath('status', 'error');
        $this->assertSame($before, $member->fresh()->getAttributes());
        $this->assertSame($adminPassword, $admin->fresh()->password);
    }

    public function test_new_signup_verification_and_existing_account_recovery_remain_available(): void
    {
        $this->postJson('/api/registerUser', ['data' => $this->signup('new@example.test')])
            ->assertOk()->assertJsonPath('needs_verification', true);
        $member = MobileUser::where('email', 'new@example.test')->firstOrFail();
        $id = $member->triumphant_id;
        $member->forceFill(['email_verification_code_hash' => Hash::make('ABC123'),
            'email_verification_expires_at' => now()->addMinutes(5)])->save();
        $this->postJson('/verifyMobileEmail', ['email' => $member->email, 'code' => 'ABC123'])
            ->assertJsonPath('status', 'ok')->assertJsonStructure(['user' => ['api_token']]);
        $this->postJson('/requestPasswordReset', ['email' => $member->email])->assertJsonPath('status', 'ok');
        $member->refresh()->forceFill(['password_reset_code_hash' => Hash::make('RST123'),
            'password_reset_expires_at' => now()->addMinutes(5)])->save();
        $this->postJson('/api/resetMobilePassword', ['email' => $member->email, 'code' => 'RST123', 'password' => 'RecoveredPassword123!'])
            ->assertJsonPath('status', 'ok');
        $this->postJson('/loginUser', ['email' => $member->email, 'password' => 'RecoveredPassword123!'])
            ->assertJsonPath('status', 'ok');
        $this->assertSame($id, $member->fresh()->triumphant_id);
        $this->assertSame(1, $member->wallet()->count());
    }

    public function test_contact_phone_and_unverified_imported_phone_never_establish_login_identity(): void
    {
        foreach ([['phone' => '+2348000000011'], ['phone_normalized' => '+2348000000012']] as $index => $fields) {
            $member = $this->member("phone{$index}@example.test", $fields);
            $before = $member->fresh()->getAttributes();
            $this->firebase('unbound-'.$index, array_values($fields)[0]);
            foreach (['/phoneAuth', '/api/phoneAuth'] as $route) {
                $this->postJson($route, ['data' => ['id_token' => 'firebase-proof']])->assertStatus(409);
            }
            $this->assertSame($before, $member->fresh()->getAttributes());
        }
    }

    public function test_authenticated_first_link_and_bound_uid_survive_contact_phone_edits_and_copies(): void
    {
        $phone = '+2348000000021';
        $copy = $this->member('copy@example.test', ['phone' => $phone]);
        $member = $this->member('owner@example.test', ['phone' => $phone]);
        $originalId = $member->triumphant_id;
        $this->firebase('owner-uid', $phone);
        $response = $this->postJson('/phoneAuth', ['data' => ['id_token' => 'firebase-proof', 'api_token' => $member->issueApiToken()]])
            ->assertJsonPath('status', 'ok')->assertJsonPath('is_new_user', false);
        $this->postJson('/updateProfile', ['api_token' => $response->json('user.api_token'),
            'fullname' => 'Original Member', 'phone' => '+2348000000099', 'gender' => 'Male', 'member_type' => 'visitor'])
            ->assertJsonPath('status', 'ok');
        $this->postJson('/api/phoneAuth', ['id_token' => 'firebase-proof'])->assertJsonPath('user.email', $member->email);
        $this->assertNull($copy->fresh()->firebase_uid);
        $this->assertSame($phone, $member->fresh()->phone_normalized);
        $this->assertNotNull($member->fresh()->phone_verified_at);
        $this->assertSame($originalId, $member->fresh()->triumphant_id);
        $this->assertSame(1, $member->wallet()->count());
    }

    public function test_uid_cannot_switch_owner_on_normalized_phone_collision(): void
    {
        $owner = $this->member('owner@example.test', ['firebase_uid' => 'owner-uid', 'phone_normalized' => '+2348000000031', 'phone_verified_at' => now()]);
        $other = $this->member('other@example.test', ['phone_normalized' => '+2348000000032']);
        $this->firebase('owner-uid', $other->phone_normalized);
        $this->postJson('/api/phoneAuth', ['id_token' => 'firebase-proof'])->assertStatus(409);
        $this->assertSame('+2348000000031', $owner->fresh()->phone_normalized);
        $this->assertNull($other->fresh()->firebase_uid);
    }

    public function test_new_phone_signup_creates_one_identity_and_wallet(): void
    {
        $this->firebase('new-uid', '+2348000000041');
        $this->postJson('/phoneAuth', ['id_token' => 'firebase-proof'])->assertJsonPath('is_new_user', true);
        $this->postJson('/api/phoneAuth', ['id_token' => 'firebase-proof'])->assertJsonPath('is_new_user', false);
        $this->assertSame(1, MobileUser::where('firebase_uid', 'new-uid')->count());
        $this->assertSame(1, MobileUser::where('firebase_uid', 'new-uid')->firstOrFail()->wallet()->count());
    }

    public function test_protected_delete_denies_before_revoking_tokens_and_allows_authorized_actor(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $target = MobileUser::where('email', $admin->email)->firstOrFail();
        $target->issueApiToken();
        $target->createToken('retained');
        $before = $target->fresh()->getAttributes();
        $manager = $this->member('manager@example.test');
        $manager->givePermissionTo(Permission::findOrCreate('delete_mobile_users', 'mobile'));
        $this->postJson('/api/control-hub/mobile-users/'.$target->id.'/delete', ['data' => ['api_token' => $manager->issueApiToken()]])->assertForbidden();
        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertSame(1, $target->tokens()->count());

        $actorAdmin = User::factory()->create();
        $actorAdmin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $actor = MobileUser::where('email', $actorAdmin->email)->firstOrFail();
        $actor->givePermissionTo(Permission::findOrCreate('delete_mobile_users', 'mobile'));
        $this->postJson('/api/control-hub/mobile-users/'.$target->id.'/delete', ['data' => ['api_token' => $actor->issueApiToken()]])
            ->assertOk()->assertJsonPath('data.user.is_deleted', true);
        $this->assertNull($target->fresh()->api_token_hash);
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_login_limit_is_shared_across_aliases_payload_shapes_and_email_case_then_expires(): void
    {
        $member = $this->member();
        for ($i = 0; $i < 5; $i++) {
            $payload = ['email' => $i % 2 ? strtoupper($member->email) : $member->email, 'password' => 'incorrect'];
            $this->postJson($i % 2 ? '/loginUser' : '/api/loginUser', $i % 2 ? ['data' => json_encode($payload)] : $payload)
                ->assertOk()->assertJsonPath('status', 'error');
        }
        $this->postJson('/loginUser', ['data' => ['email' => $member->email, 'password' => 'OriginalPassword123!']])
            ->assertStatus(429)->assertHeader('Retry-After')->assertJsonMissing(['password' => 'OriginalPassword123!']);
        $this->travel(61)->seconds();
        $this->postJson('/api/loginUser', ['email' => $member->email, 'password' => 'OriginalPassword123!'])->assertJsonPath('status', 'ok');
    }

    public function test_verification_issuance_cooldown_is_shared_by_login_and_resend(): void
    {
        $this->mock(DynamicSmtpMailer::class)->shouldReceive('sendRaw')->once()->andReturnNull();
        $member = $this->member('pending@example.test', ['is_verified' => false, 'email_verified_at' => null]);
        $this->postJson('/loginUser', ['email' => $member->email, 'password' => 'OriginalPassword123!'])->assertJsonPath('needs_verification', true);
        $hash = $member->fresh()->email_verification_code_hash;
        $this->postJson('/api/loginUser', ['email' => $member->email, 'password' => 'OriginalPassword123!'])
            ->assertOk()->assertJsonPath('needs_verification', true);
        $this->assertSame($hash, $member->fresh()->email_verification_code_hash);
        $this->postJson('/api/resendMobileVerification', ['data' => ['email' => strtoupper($member->email)]])->assertStatus(429);
        $this->assertSame($hash, $member->fresh()->email_verification_code_hash);
    }

    public function test_new_signup_transfers_verification_cooldown_to_created_account_identity(): void
    {
        $this->mock(DynamicSmtpMailer::class)->shouldReceive('sendRaw')->once()->andReturnNull();
        $email = 'signup-cooldown@example.test';
        $this->postJson('/registerUser', ['data' => $this->signup($email)])->assertJsonPath('needs_verification', true);
        $member = MobileUser::where('email', $email)->firstOrFail();
        $hash = $member->email_verification_code_hash;
        $this->postJson('/api/loginUser', ['email' => strtoupper($email), 'password' => 'AttackerPassword123!'])
            ->assertOk()->assertJsonPath('needs_verification', true);
        $this->postJson('/api/resendMobileVerification', ['email' => $email])->assertStatus(429);
        $this->assertSame($hash, $member->fresh()->email_verification_code_hash);
    }

    public function test_equivalent_emails_resolving_to_one_account_share_authentication_budgets(): void
    {
        // SQLite does not reproduce MySQL LOWER(email) accent-insensitive equality.
        // Emulate only that resolution boundary and exercise the real HTTP limiters.
        $this->app->instance(CompatibilityController::class, new class extends CompatibilityController
        {
            protected function authAccount(array $data): ?MobileUser
            {
                $data['email'] = str_replace('é', 'e', $data['email'] ?? '');

                return parent::authAccount($data);
            }
        });
        $member = $this->member();
        $alias = 'mémber@example.test';
        $this->postJson('/loginUser', ['email' => $alias, 'password' => 'OriginalPassword123!'])
            ->assertJsonPath('status', 'ok')->assertJsonPath('user.email', $member->email);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson($i % 2 ? '/api/loginUser' : '/loginUser',
                ['email' => $i % 2 ? $alias : $member->email, 'password' => 'incorrect'])
                ->assertOk()->assertJsonPath('status', 'error');
            $this->postJson($i % 2 ? '/api/verifyMobileEmail' : '/verifyMobileEmail',
                ['email' => $i % 2 ? $alias : $member->email, 'code' => 'WRONG1'])
                ->assertOk()->assertJsonPath('status', 'error');
        }
        $this->postJson('/api/loginUser', ['email' => $alias, 'password' => 'OriginalPassword123!'])->assertStatus(429);
        $this->postJson('/verifyMobileEmail', ['email' => $alias, 'code' => 'WRONG1'])->assertStatus(429);
        $this->postJson('/requestPasswordReset', ['email' => $member->email])->assertOk();
        $this->postJson('/api/resetPassword', ['email' => $alias])->assertStatus(429);
    }

    public function test_reset_issuance_aliases_and_code_attempts_are_bounded(): void
    {
        $member = $this->member();
        $this->postJson('/resetPassword', ['email' => $member->email])->assertOk();
        $hash = $member->fresh()->password_reset_code_hash;
        foreach (['/api/requestPasswordReset', '/api/resetPassword', '/requestPasswordReset'] as $route) {
            $this->postJson($route, ['data' => ['email' => strtoupper($member->email)]])->assertStatus(429);
        }
        $this->assertSame($hash, $member->fresh()->password_reset_code_hash);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($i % 2 ? '/resetMobilePassword' : '/api/resetMobilePassword',
                ['email' => $member->email, 'code' => 'WRONG1', 'password' => 'NewPassword123!'])->assertJsonPath('status', 'error');
        }
        $this->postJson('/resetMobilePassword', ['email' => $member->email, 'code' => 'WRONG1', 'password' => 'NewPassword123!'])->assertStatus(429);
        $this->assertTrue(Hash::check('OriginalPassword123!', $member->fresh()->password));
    }

    public function test_verification_attempts_are_bounded_and_disabled_accounts_cannot_verify(): void
    {
        $member = $this->member('blocked@example.test', ['is_blocked' => true, 'email_verification_code_hash' => Hash::make('ABC123'), 'email_verification_expires_at' => now()->addMinutes(5)]);
        $this->postJson('/verifyMobileEmail', ['email' => $member->email, 'code' => 'ABC123'])->assertJsonPath('status', 'error');
        $this->assertNull($member->fresh()->api_token_hash);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/verifyMobileEmail', ['email' => $member->email, 'code' => 'WRONG1'])->assertOk();
        }
        $this->postJson('/verifyMobileEmail', ['email' => $member->email, 'code' => 'ABC123'])->assertStatus(429);
    }

    public function test_codes_without_expiry_cannot_verify_or_reset(): void
    {
        $member = $this->member('missing-expiry@example.test', ['is_verified' => false, 'email_verified_at' => null,
            'email_verification_code_hash' => Hash::make('ABC123'), 'password_reset_code_hash' => Hash::make('RST123')]);
        $this->postJson('/verifyMobileEmail', ['email' => $member->email, 'code' => 'ABC123'])->assertJsonPath('status', 'error');
        $this->postJson('/resetMobilePassword', ['email' => $member->email, 'code' => 'RST123', 'password' => 'NewPassword123!'])->assertJsonPath('status', 'error');
        $this->assertNull($member->fresh()->api_token_hash);
        $this->assertTrue(Hash::check('OriginalPassword123!', $member->fresh()->password));
    }
}
