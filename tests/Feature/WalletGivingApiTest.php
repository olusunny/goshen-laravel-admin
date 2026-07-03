<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\DonationCategory;
use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\AppSetting;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletGivingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_giving_status_returns_iso_currency_when_admin_setting_uses_symbol(): void
    {
        AppSetting::query()->create([
            'key' => 'currency',
            'value' => '£',
        ]);

        $this->getJson('/api/giving/stripe/status')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('currency', 'GBP');
    }

    public function test_wallet_giving_requires_signed_in_verified_user(): void
    {
        $category = $this->category();
        $payload = [
            'data' => [
                'amount' => 25,
                'currency' => 'GBP',
                'donation_category_id' => $category->id,
                'anonymous' => false,
                'idempotency_key' => 'wallet-giving-auth-required-key',
            ],
        ];

        $this->postJson('/api/giving/wallet/pay', $payload)
            ->assertStatus(401)
            ->assertJsonPath('status', 'error');

        $member = $this->member('unverified-giver@example.test', 'Unverified Giver');
        $member->forceFill([
            'is_verified' => false,
            'email_verified_at' => null,
        ])->save();
        $token = $member->issueApiToken();
        $this->wallet($member, 100);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/wallet/pay', $payload)
            ->assertStatus(403)
            ->assertJsonPath('status', 'error');

        $this->assertSame(0, Donation::query()->count());
    }

    public function test_wallet_giving_requires_clear_wallet_security_state(): void
    {
        $member = $this->member('reset-giver@example.test', 'Reset Giver');
        $token = $member->issueApiToken();
        $wallet = $this->wallet($member, 100);
        $category = $this->category();

        $member->forceFill([
            'wallet_security_reset_required' => true,
            'wallet_security_reset_requested_at' => now(),
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/wallet/pay', [
                'data' => [
                    'amount' => 25,
                    'currency' => 'GBP',
                    'donation_category_id' => $category->id,
                    'anonymous' => false,
                    'idempotency_key' => 'wallet-giving-security-reset-key',
                ],
            ])
            ->assertStatus(423)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('wallet_security_reset.reset_required', true);

        $this->assertSame('100.00', $wallet->fresh()->balance);
        $this->assertSame(0, Donation::query()->count());
        $this->assertSame(0, GoshenWalletLedgerEntry::query()->where('type', 'giving_payment')->count());
    }

    public function test_wallet_giving_debits_once_and_is_idempotent(): void
    {
        $member = $this->member('giver@example.test', 'Wallet Giver');
        $token = $member->issueApiToken();
        $wallet = $this->wallet($member, 100);
        $category = $this->category();

        $payload = [
            'data' => [
                'amount' => 25,
                'currency' => 'GBP',
                'donation_category_id' => $category->id,
                'name' => 'Forged Name',
                'email' => 'other-member@example.test',
                'phone' => '+440000000000',
                'anonymous' => false,
                'idempotency_key' => 'wallet-giving-test-key',
            ],
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/wallet/pay', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('donation.payment_method', 'wallet')
            ->assertJsonPath('idempotent_replay', false);

        $donation = Donation::query()->firstOrFail();
        $this->assertSame('wallet', $donation->provider);
        $this->assertSame('paid', $donation->status);
        $this->assertSame('Wallet Giver', $donation->name);
        $this->assertSame('giver@example.test', $donation->email);
        $this->assertSame($category->id, $donation->donation_category_id);
        $this->assertSame('75.00', $wallet->fresh()->balance);
        $this->assertSame(1, Donation::query()->count());
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'giving_payment')->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/wallet/pay', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('idempotent_replay', true);

        $this->assertSame('75.00', $wallet->fresh()->balance);
        $this->assertSame(1, Donation::query()->count());
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'giving_payment')->count());
    }

    public function test_reused_idempotency_key_with_different_details_is_rejected(): void
    {
        $member = $this->member('giver@example.test', 'Wallet Giver');
        $token = $member->issueApiToken();
        $wallet = $this->wallet($member, 100);
        $category = $this->category();

        $base = [
            'currency' => 'GBP',
            'donation_category_id' => $category->id,
            'anonymous' => false,
            'idempotency_key' => 'wallet-giving-test-key',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/wallet/pay', ['data' => $base + ['amount' => 25]])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/wallet/pay', ['data' => $base + ['amount' => 30]])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertSame('75.00', $wallet->fresh()->balance);
        $this->assertSame(1, Donation::query()->count());
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'giving_payment')->count());
    }

    private function member(string $email, string $name): MobileUser
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

    private function category(): DonationCategory
    {
        return DonationCategory::query()->where('slug', 'offering')->firstOrFail();
    }
}
