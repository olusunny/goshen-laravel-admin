<?php

namespace Tests\Feature;

use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Models\User;
use App\Models\WalletSecurityResetRequest;
use App\Services\WalletSecurityResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WalletSecurityResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reset_creates_audit_row_and_invalidates_mobile_session(): void
    {
        $admin = $this->admin();
        $member = $this->member('member@example.test');
        $oldToken = $member->issueApiToken();

        $reset = app(WalletSecurityResetService::class)->requestReset(
            $member,
            $admin,
            'registered_email_or_phone',
            'Verified registered email and recent wallet activity.',
            '127.0.0.1',
            'Feature test',
        );

        $member->refresh();

        $this->assertSame(WalletSecurityResetRequest::STATUS_PENDING, $reset->status);
        $this->assertTrue($member->wallet_security_reset_required);
        $this->assertNull($member->api_token_hash);
        $this->assertDatabaseHas('wallet_security_reset_requests', [
            'id' => $reset->id,
            'mobile_user_id' => $member->id,
            'admin_user_id' => $admin->id,
            'status' => WalletSecurityResetRequest::STATUS_PENDING,
            'invalidated_mobile_session' => true,
        ]);

        $this->postJson('/api/goshen-wallet/security-reset/status', [
            'data' => ['api_token' => $oldToken],
        ])->assertUnauthorized();
    }

    public function test_member_can_acknowledge_reset_after_signing_in_again(): void
    {
        $admin = $this->admin();
        $member = $this->member('member@example.test');

        app(WalletSecurityResetService::class)->requestReset(
            $member,
            $admin,
            'registered_email_or_phone',
            'Verified support caller against account profile.',
        );

        $newToken = $member->fresh()->issueApiToken();

        $this->postJson('/api/goshen-wallet/security-reset/status', [
            'data' => ['api_token' => $newToken],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.reset_required', true);

        $this->postJson('/api/goshen-wallet/security-reset/acknowledge', [
            'data' => ['api_token' => $newToken],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.reset_required', false);

        $member->refresh();

        $this->assertFalse($member->wallet_security_reset_required);
        $this->assertNotNull($member->wallet_security_reset_acknowledged_at);
        $this->assertDatabaseHas('wallet_security_reset_requests', [
            'mobile_user_id' => $member->id,
            'status' => WalletSecurityResetRequest::STATUS_ACKNOWLEDGED,
        ]);
    }

    public function test_pending_reset_blocks_wallet_transfer_until_acknowledged(): void
    {
        $admin = $this->admin();
        $sender = $this->member('sender@example.test', 'Sender Member');
        $recipient = $this->member('recipient@example.test', 'Recipient Member');
        $senderWallet = $this->wallet($sender, 100);
        $recipientWallet = $this->wallet($recipient, 10);

        app(WalletSecurityResetService::class)->requestReset(
            $sender,
            $admin,
            'registered_email_or_phone',
            'Verified damaged phone support request.',
        );

        $newToken = $sender->fresh()->issueApiToken();

        $this->postJson('/api/goshen-wallet/transfer', [
            'data' => [
                'api_token' => $newToken,
                'recipient' => $recipient->email,
                'amount' => 25,
                'currency' => 'GBP',
            ],
        ])
            ->assertStatus(423)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('wallet_security_reset.reset_required', true);

        $this->assertSame('100.00', $senderWallet->fresh()->balance);
        $this->assertSame('10.00', $recipientWallet->fresh()->balance);

        $this->postJson('/api/goshen-wallet/security-reset/acknowledge', [
            'data' => ['api_token' => $newToken],
        ])->assertOk();

        $this->postJson('/api/goshen-wallet/transfer', [
            'data' => [
                'api_token' => $newToken,
                'recipient' => $recipient->email,
                'amount' => 25,
                'currency' => 'GBP',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertSame('75.00', $senderWallet->fresh()->balance);
        $this->assertSame('35.00', $recipientWallet->fresh()->balance);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    private function member(string $email, string $name = 'Member Test'): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '+447700900' . random_int(100, 999),
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->create([
            'mobile_user_id' => $member->id,
            'currency' => 'GBP',
            'balance' => $balance,
        ]);
    }
}
