<?php

namespace Tests\Feature;

use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\GoshenWalletSavingsPlan;
use App\Models\MobileUser;
use App\Services\GoshenWalletService;
use App\Services\GoshenRetreatNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    public function test_failed_scheduled_top_up_records_visible_retries_then_pauses_plan(): void
    {
        $member = $this->member();
        $wallet = $this->wallet($member);
        $wallet->forceFill([
            'stripe_customer_id' => 'cus_retry_test',
            'stripe_payment_method_id' => 'pm_retry_test',
        ])->save();
        $plan = $wallet->savingsPlans()->create([
            'status' => 'active',
            'frequency' => 'daily',
            'interval_count' => 1,
            'amount' => 10,
            'currency' => 'GBP',
            'next_charge_at' => now()->subMinute(),
            'metadata' => [],
        ]);

        $service = app(GoshenWalletService::class);
        $failure = new \ReflectionMethod($service, 'recordScheduledTopUpFailure');
        $failure->setAccessible(true);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $plan->refresh();
            $dueAt = $plan->next_charge_at->copy();
            $reference = 'gw_auto_' . $plan->id . '_' . $dueAt->copy()->utc()->format('YmdHis');

            $entry = $failure->invoke(
                $service,
                $plan,
                $wallet,
                $reference,
                $dueAt,
                'Stripe card declined.',
                'stripe_exception',
                null,
            );

            $this->assertInstanceOf(GoshenWalletLedgerEntry::class, $entry);
            $this->assertSame('failed', $entry->status);
            $this->assertSame($attempt, $entry->metadata['failed_attempt_number']);
            $this->assertSame($attempt <= 3, $entry->metadata['will_retry']);

            if ($attempt <= 3) {
                $this->assertSame('active', $plan->fresh()->status);
                $this->assertNotNull($plan->fresh()->next_charge_at);
                $this->travelTo($plan->fresh()->next_charge_at);
            }
        }

        $plan->refresh();
        $this->assertSame('paused', $plan->status);
        $this->assertNull($plan->next_charge_at);
        $this->assertSame(4, GoshenWalletLedgerEntry::query()->where('type', 'scheduled_top_up')->count());

        $payload = $service->payload($wallet->fresh());
        $failedTopUps = collect($payload['ledger'])
            ->where('type', 'scheduled_top_up')
            ->values();

        $this->assertCount(4, $failedTopUps);
        $this->assertSame('failed', $failedTopUps->first()['status']);
        $this->assertSame(4, $failedTopUps->first()['metadata']['failed_attempt_number']);
        $this->assertStringContainsString('Automatic wallet top-up failed', $failedTopUps->first()['description']);
    }

    public function test_scheduler_notifies_wallet_owner_about_failed_retry(): void
    {
        $member = $this->member();
        $wallet = $this->wallet($member);
        $plan = $wallet->savingsPlans()->create([
            'status' => 'active',
            'frequency' => 'daily',
            'interval_count' => 1,
            'amount' => 10,
            'currency' => 'GBP',
            'next_charge_at' => now()->subMinute(),
            'metadata' => [],
        ]);
        $entry = $wallet->ledgerEntries()->create([
            'type' => 'scheduled_top_up',
            'status' => 'failed',
            'currency' => 'GBP',
            'amount' => 10,
            'gateway' => 'stripe',
            'provider_reference' => 'gw_auto_notify',
            'metadata' => [
                'will_retry' => true,
                'next_retry_at' => now()->addMinutes(15)->toIso8601String(),
                'retries_remaining' => 3,
            ],
        ]);

        $wallets = Mockery::mock(GoshenWalletService::class);
        $wallets->shouldReceive('chargeDuePlan')
            ->once()
            ->with(Mockery::on(fn (GoshenWalletSavingsPlan $duePlan): bool => $duePlan->is($plan)))
            ->andReturn($entry);
        $this->app->instance(GoshenWalletService::class, $wallets);

        $notifications = Mockery::mock(GoshenRetreatNotificationService::class);
        $notifications->shouldReceive('notifyUser')
            ->once()
            ->with(
                Mockery::on(fn (MobileUser $user): bool => $user->is($member)),
                'Goshen wallet top-up failed',
                Mockery::on(fn (string $body): bool => str_contains($body, 'retry automatically') && str_contains($body, 'Retries remaining: 3')),
                'wallet',
            );
        $this->app->instance(GoshenRetreatNotificationService::class, $notifications);

        $this->artisan('goshen:process-wallet-topups')->assertExitCode(0);
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
