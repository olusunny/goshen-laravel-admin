<?php

namespace Tests\Feature;

use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\GoshenWalletSavingsPlan;
use App\Models\MobileUser;
use App\Services\GoshenWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoshenWalletAutoTopUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_savings_plan_without_saved_card_waits_for_checkout_setup(): void
    {
        $member = $this->member();
        $wallet = $this->wallet($member);

        $plan = app(GoshenWalletService::class)->createSavingsPlan($wallet, [
            'amount' => 10,
            'currency' => 'GBP',
            'frequency' => 'daily',
        ]);

        $this->assertSame('setup_required', $plan->status);
        $this->assertNull($plan->next_charge_at);
        $this->assertTrue($plan->metadata['requires_checkout_setup']);
    }

    public function test_saved_card_checkout_activates_setup_required_plan(): void
    {
        $member = $this->member();
        $wallet = $this->wallet($member);
        $plan = $wallet->savingsPlans()->create([
            'status' => 'setup_required',
            'frequency' => 'daily',
            'interval_count' => 1,
            'amount' => 10,
            'currency' => 'GBP',
            'next_charge_at' => null,
            'metadata' => ['requires_checkout_setup' => true],
        ]);
        $entry = $wallet->ledgerEntries()->create([
            'type' => 'top_up',
            'status' => 'pending',
            'currency' => 'GBP',
            'amount' => 10,
            'gateway' => 'stripe',
            'provider_reference' => 'gw_test_setup',
            'metadata' => [
                'savings_plan_id' => $plan->id,
                'save_payment_method' => true,
            ],
        ]);

        app(GoshenWalletService::class)->settleStripeCheckout([
            'id' => 'evt_wallet_setup',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_wallet_setup',
                    'client_reference_id' => $entry->provider_reference,
                    'payment_status' => 'paid',
                    'currency' => 'gbp',
                    'amount_total' => 1000,
                    'customer' => 'cus_wallet_setup',
                    'payment_method' => 'pm_wallet_setup',
                    'metadata' => [
                        'integration' => 'goshen_wallet',
                        'reference' => $entry->provider_reference,
                    ],
                ],
            ],
        ]);

        $wallet->refresh();
        $plan->refresh();
        $entry->refresh();

        $this->assertSame('10.00', $wallet->balance);
        $this->assertSame('cus_wallet_setup', $wallet->stripe_customer_id);
        $this->assertSame('pm_wallet_setup', $wallet->stripe_payment_method_id);
        $this->assertSame('paid', $entry->status);
        $this->assertSame('active', $plan->status);
        $this->assertNotNull($plan->next_charge_at);
        $this->assertTrue($plan->next_charge_at->isFuture());
        $this->assertFalse($plan->metadata['requires_checkout_setup']);
    }

    private function member(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Wallet Saver',
            'email' => 'wallet-saver@example.test',
            'phone' => '+447700900123',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function wallet(MobileUser $member): GoshenWallet
    {
        return GoshenWallet::query()->create([
            'mobile_user_id' => $member->id,
            'currency' => 'GBP',
            'balance' => 0,
        ]);
    }
}
