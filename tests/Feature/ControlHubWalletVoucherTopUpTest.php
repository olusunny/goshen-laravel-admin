<?php

namespace Tests\Feature;

use App\Models\GoshenVoucher;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\GoshenVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControlHubWalletVoucherTopUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_control_hub_admin_can_redeem_wallet_funding_voucher_for_member(): void
    {
        $admin = $this->member('wallet-admin@example.test', 'Wallet Admin');
        $token = $admin->issueApiToken();
        Permission::findOrCreate('redeem_wallet_funding_vouchers_for_members', 'mobile');
        $admin->givePermissionTo('redeem_wallet_funding_vouchers_for_members');

        $member = $this->member('wallet-beneficiary@example.test', 'Wallet Beneficiary');
        $wallet = $this->wallet($member, 10.01);
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'label' => 'Wallet Funding Voucher',
            'amount' => 25.01,
            'currency' => 'GBP',
            'max_uses' => 1,
            'purpose' => GoshenVoucher::PURPOSE_WALLET_FUNDING,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/control-hub/mobile-users/{$member->id}/wallet/voucher", [
                'data' => [
                    'api_token' => $token,
                    'code' => $voucher['code'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('usage.source', 'control_hub_wallet_voucher_top_up')
            ->assertJsonPath('data.balance', 35.02);

        $this->assertSame('35.02', $wallet->fresh()->balance);
        $this->assertDatabaseHas('goshen_voucher_usages', [
            'voucher_id' => $voucher['voucher']->id,
            'mobile_user_id' => $member->id,
            'redeemed_by_mobile_user_id' => $admin->id,
            'source' => 'control_hub_wallet_voucher_top_up',
        ]);
        $this->assertDatabaseHas('goshen_wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => 'voucher_top_up',
            'status' => 'paid',
            'gateway' => 'voucher',
            'amount' => 25.01,
        ]);
    }

    public function test_control_hub_rejects_payment_vouchers_and_unauthorized_admins(): void
    {
        $member = $this->member('protected-beneficiary@example.test', 'Protected Beneficiary');
        $wallet = $this->wallet($member, 0);
        $unauthorized = $this->member('unauthorized@example.test', 'Unauthorized');
        $token = $unauthorized->issueApiToken();
        $paymentVoucher = app(GoshenVoucherService::class)->createVoucher([
            'label' => 'Payment Voucher',
            'amount' => 20,
            'currency' => 'GBP',
            'max_uses' => 1,
            'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/control-hub/mobile-users/{$member->id}/wallet/voucher", [
                'data' => ['api_token' => $token, 'code' => $paymentVoucher['code']],
            ])
            ->assertForbidden();

        Permission::findOrCreate('redeem_wallet_funding_vouchers_for_members', 'mobile');
        $unauthorized->givePermissionTo('redeem_wallet_funding_vouchers_for_members');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/control-hub/mobile-users/{$member->id}/wallet/voucher", [
                'data' => ['api_token' => $token, 'code' => $paymentVoucher['code']],
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertSame('0.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('goshen_voucher_usages', 0);
    }

    private function member(string $email, string $name): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '+447700900123',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->updateOrCreate(
            ['mobile_user_id' => $member->id],
            ['currency' => 'GBP', 'balance' => $balance],
        );
    }
}
