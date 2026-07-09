<?php

namespace Tests\Feature;

use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\GoshenWalletWithdrawalRequest;
use App\Models\MobileUser;
use App\Services\GoshenVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenWalletVoucherWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_fund_wallet_with_voucher_once(): void
    {
        $member = $this->member('voucher-wallet@example.test', 'Voucher Wallet');
        $token = $member->issueApiToken();
        $wallet = $this->wallet($member, 10);
        $code = $this->walletVoucherCode(25);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/goshen-wallet/top-up/voucher', [
                'data' => [
                    'api_token' => $token,
                    'code' => $code,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.balance', 35);

        $this->assertSame('35.00', $wallet->fresh()->balance);
        $this->assertDatabaseHas('goshen_wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => 'voucher_top_up',
            'status' => 'paid',
            'gateway' => 'voucher',
        ]);
        $this->assertDatabaseHas('goshen_voucher_usages', [
            'mobile_user_id' => $member->id,
            'source' => 'wallet_top_up',
            'amount' => 25,
        ]);
        $this->assertSame(GoshenVoucher::STATUS_EXHAUSTED, GoshenVoucher::query()->firstOrFail()->status);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/goshen-wallet/top-up/voucher', [
                'data' => ['api_token' => $token, 'code' => $code],
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertSame('35.00', $wallet->fresh()->balance);
        $this->assertSame(1, GoshenVoucherUsage::query()->count());
    }

    public function test_withdrawal_request_reserves_funds_and_member_can_cancel_pending_request(): void
    {
        $member = $this->member('withdrawer@example.test', 'Wallet Withdrawer');
        $token = $member->issueApiToken();
        $wallet = $this->wallet($member, 100);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/goshen-wallet/withdrawals', [
                'data' => $this->withdrawalPayload($token, 40),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.balance', 60)
            ->assertJsonPath('withdrawal.status', GoshenWalletWithdrawalRequest::STATUS_PENDING);

        $request = GoshenWalletWithdrawalRequest::query()->firstOrFail();
        $this->assertSame('60.00', $wallet->fresh()->balance);
        $this->assertDatabaseHas('goshen_wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal_request',
            'status' => 'pending',
            'amount' => 40,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/goshen-wallet/withdrawals/{$request->id}/cancel", [
                'data' => ['api_token' => $token],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.balance', 100)
            ->assertJsonPath('withdrawal.status', GoshenWalletWithdrawalRequest::STATUS_CANCELLED);

        $this->assertSame('100.00', $wallet->fresh()->balance);
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'withdrawal_refund')->count());
    }

    public function test_only_authorized_manager_can_manage_withdrawals_and_mark_paid(): void
    {
        $member = $this->member('managed-withdrawal@example.test', 'Managed Withdrawal');
        $memberToken = $member->issueApiToken();
        $wallet = $this->wallet($member, 100);

        $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->postJson('/api/goshen-wallet/withdrawals', [
                'data' => $this->withdrawalPayload($memberToken, 45),
            ])
            ->assertOk();

        $request = GoshenWalletWithdrawalRequest::query()->firstOrFail();
        $regular = $this->member('regular-withdrawal@example.test', 'Regular Member');
        $regularToken = $regular->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$regularToken)
            ->postJson('/api/goshen-wallet/withdrawals/management', [
                'data' => ['api_token' => $regularToken],
            ])
            ->assertForbidden();

        $manager = $this->manager();
        $managerToken = $manager->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson('/api/goshen-wallet/withdrawals/management', [
                'data' => ['api_token' => $managerToken],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonCount(1, 'data.requests');

        $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson("/api/goshen-wallet/withdrawals/{$request->id}/management-status", [
                'data' => [
                    'api_token' => $managerToken,
                    'status' => 'approved',
                    'admin_note' => 'Verified bank details.',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('withdrawal.status', GoshenWalletWithdrawalRequest::STATUS_APPROVED);

        $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson("/api/goshen-wallet/withdrawals/{$request->id}/management-status", [
                'data' => [
                    'api_token' => $managerToken,
                    'status' => 'paid',
                    'payout_reference' => 'BANK-PAYOUT-1',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('withdrawal.status', GoshenWalletWithdrawalRequest::STATUS_PAID)
            ->assertJsonPath('withdrawal.payout_reference', 'BANK-PAYOUT-1');

        $this->assertSame('55.00', $wallet->fresh()->balance);
        $this->assertSame('paid', $request->fresh()->ledgerEntry->status);
    }

    private function member(string $email, string $name): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '+447700900'.random_int(100, 999),
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function manager(): MobileUser
    {
        Permission::findOrCreate('manage_goshen_wallet_withdrawals', 'mobile');
        $role = Role::findOrCreate('event_manager', 'mobile');
        $role->givePermissionTo('manage_goshen_wallet_withdrawals');

        $manager = $this->member('wallet-manager@example.test', 'Wallet Manager');
        $manager->assignRole($role);

        return $manager;
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->create([
            'mobile_user_id' => $member->id,
            'currency' => 'GBP',
            'balance' => $balance,
        ]);
    }

    private function walletVoucherCode(float $amount): string
    {
        $created = app(GoshenVoucherService::class)->createVoucher([
            'label' => 'Wallet voucher',
            'amount' => $amount,
            'currency' => 'GBP',
            'max_uses' => 1,
            'metadata' => ['purpose' => 'wallet_top_up'],
        ]);

        return $created['code'];
    }

    private function withdrawalPayload(string $token, float $amount): array
    {
        return [
            'api_token' => $token,
            'amount' => $amount,
            'currency' => 'GBP',
            'bank_name' => 'Test Bank',
            'account_name' => 'Wallet Withdrawer',
            'account_number' => '12345678',
            'sort_code' => '10-20-30',
            'user_note' => 'Please process after service.',
        ];
    }
}
