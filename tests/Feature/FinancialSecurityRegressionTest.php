<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DonationStripeController;
use App\Models\AppSetting;
use App\Models\Donation;
use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use App\Services\FundraisingWalletGateway;
use App\Services\GoshenRetreatNotificationService;
use App\Services\GoshenWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Http\Controllers\Api\PaymentWebhookController;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentGatewayWebhookEvent;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\Gateways\StripeGateway;
use Personal\EventInstallments\Services\LatePaymentRefundReconciler;
use Personal\EventInstallments\Services\PaymentGatewayManager;
use Personal\EventInstallments\Services\PaymentSettlementService;
use ReflectionMethod;
use RuntimeException;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;
use Sunny\Fundraising\Services\CampaignService;
use Tests\TestCase;

class FinancialSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_resolution_uses_only_unique_verified_canonical_contacts(): void
    {
        $this->member(['phone' => 'not-a-number']);
        $this->member(['phone' => '9001234']);
        $this->member(['phone' => '+447700900123']);
        $recipient = $this->member([
            'phone' => 'old display number', 'phone_normalized' => '+447700900123', 'phone_verified_at' => now(),
        ]);
        $resolver = new ReflectionMethod(GoshenWalletService::class, 'findTransferRecipient');
        $service = app(GoshenWalletService::class);

        foreach (['+44 (7700) 900-123', '0044 7700 900123', '447700900123'] as $identifier) {
            $this->assertSame($recipient->id, $resolver->invoke($service, $identifier)?->id);
        }
        foreach (['900123', '07700900123', 'call447700900123', 'not-a-number'] as $identifier) {
            $this->assertNull($resolver->invoke($service, $identifier));
        }
        $this->assertSame($recipient->id, $resolver->invoke($service, strtoupper($recipient->email))?->id);

        $this->member(['phone_normalized' => '00447700900123', 'phone_verified_at' => now()]);
        $this->assertNull($resolver->invoke($service, '+447700900123'));
    }

    public function test_unverified_imported_phone_cannot_receive_a_transfer(): void
    {
        $sender = $this->member();
        $recipient = $this->member(['phone_normalized' => '+447700900123', 'phone_verified_at' => null]);
        $wallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $sender->id], ['currency' => 'GBP', 'balance' => 100]);
        $recipientWallet = app(GoshenWalletService::class)->walletFor($recipient);

        try {
            app(GoshenWalletService::class)->transfer($wallet, '+447700900123', 25, 'GBP');
            $this->fail('An unverified contact must not resolve to a transfer recipient.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('confirm the recipient', $exception->getMessage());
        }

        $this->assertSame('100.00', $wallet->fresh()->balance);
        $this->assertSame('0.00', $recipientWallet->fresh()->balance);
        $this->assertSame(0, GoshenWalletLedgerEntry::query()->whereIn('type', ['transfer_in', 'transfer_out'])->count());
    }

    public function test_verified_phone_does_not_make_an_unverified_email_eligible_for_transfers(): void
    {
        $this->mock(GoshenRetreatNotificationService::class)->shouldNotReceive('notifyUser');
        $sender = $this->member();
        $recipient = $this->member([
            'is_verified' => true, 'email_verified_at' => null,
            'phone_normalized' => '+447700900123', 'phone_verified_at' => now(),
        ]);
        $wallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $sender->id], ['currency' => 'GBP', 'balance' => 100]);
        $recipientWallet = app(GoshenWalletService::class)->walletFor($recipient);

        try {
            app(GoshenWalletService::class)->transfer($wallet, $recipient->email, 25, 'GBP');
            $this->fail('Phone verification must not establish ownership of an email address.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('confirm the recipient', $exception->getMessage());
        }

        $this->assertSame('100.00', $wallet->fresh()->balance);
        $this->assertSame('0.00', $recipientWallet->fresh()->balance);
        $this->assertSame(0, GoshenWalletLedgerEntry::query()->whereIn('type', ['transfer_in', 'transfer_out'])->count());
    }

    public function test_verified_email_and_phone_transfers_conserve_gbp_and_record_correct_recipients(): void
    {
        $this->mock(GoshenRetreatNotificationService::class)
            ->shouldReceive('notifyUser')->times(4)->andReturn(new InboxMessage);
        $sender = $this->member();
        $emailRecipient = $this->member();
        $phoneRecipient = $this->member([
            'email_verified_at' => null, 'phone_normalized' => '+447700900123', 'phone_verified_at' => now(),
        ]);
        $senderWallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $sender->id], ['currency' => 'GBP', 'balance' => 100]);
        $emailWallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $emailRecipient->id], ['currency' => 'GBP', 'balance' => 5]);
        $phoneWallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $phoneRecipient->id], ['currency' => 'GBP', 'balance' => 10]);

        foreach ([[$emailRecipient, $emailWallet, strtoupper($emailRecipient->email)], [$phoneRecipient, $phoneWallet, '0044 (7700) 900-123']] as [$recipient, $recipientWallet, $identifier]) {
            $result = app(GoshenWalletService::class)->transfer($senderWallet, $identifier, 25, 'GBP');
            $outgoing = $result['sender_entry']->fresh();
            $incoming = $result['recipient_entry']->fresh();
            $this->assertSame($senderWallet->id, $outgoing->wallet_id);
            $this->assertSame($recipientWallet->id, $incoming->wallet_id);
            $this->assertSame($recipient->id, $outgoing->metadata['recipient_id']);
            $this->assertSame($sender->id, $incoming->metadata['sender_id']);
            $this->assertSame($outgoing->metadata['transfer_reference'], $incoming->metadata['transfer_reference']);
            $this->assertSame('transfer_out', $outgoing->type);
            $this->assertSame('transfer_in', $incoming->type);
            $this->assertSame('25.00', $outgoing->amount);
            $this->assertSame($outgoing->amount, $incoming->amount);
            $this->assertSame('GBP', $outgoing->currency);
            $this->assertSame('GBP', $incoming->currency);
            $this->assertSame('paid', $outgoing->status);
            $this->assertSame('paid', $incoming->status);
            $this->assertSame(115.0, (float) $senderWallet->fresh()->balance + (float) $emailWallet->fresh()->balance + (float) $phoneWallet->fresh()->balance);
        }

        $this->assertSame('50.00', $senderWallet->fresh()->balance);
        $this->assertSame('30.00', $emailWallet->fresh()->balance);
        $this->assertSame('35.00', $phoneWallet->fresh()->balance);
        $this->assertSame(4, GoshenWalletLedgerEntry::query()->whereIn('type', ['transfer_in', 'transfer_out'])->count());
    }

    public function test_unpaid_completed_checkout_waits_for_verified_async_success(): void
    {
        $donation = $this->donation();
        $this->givingWebhook($donation, 'checkout.session.completed', ['payment_status' => 'unpaid'], 'evt_unpaid');
        $this->assertSame('pending', $donation->fresh()->status);
        $this->assertNull($donation->fresh()->paid_at);

        $this->givingWebhook($donation, 'checkout.session.async_payment_succeeded', ['payment_status' => 'paid'], 'evt_paid');
        $this->assertSame('paid', $donation->fresh()->status);
        $this->assertNotNull($donation->fresh()->paid_at);
        $this->givingWebhook($donation, 'checkout.session.async_payment_failed', [], 'evt_late_failure');
        $this->assertSame('paid', $donation->fresh()->status);
    }

    public function test_giving_requires_paid_status_and_matching_complete_payment_evidence(): void
    {
        foreach ([['payment_status' => null], ['currency' => null], ['amount_total' => null], ['currency' => 'usd'], ['amount_total' => 100]] as $overrides) {
            $donation = $this->donation();
            $this->givingWebhook($donation, 'checkout.session.completed', $overrides, 'evt_'.str()->random(10));
            $this->assertSame('pending', $donation->fresh()->status);
            $this->assertNull($donation->fresh()->paid_at);
        }
        $donation = $this->donation();
        $this->givingWebhook($donation, 'checkout.session.completed', [], 'evt_complete_paid');
        $this->assertSame('paid', $donation->fresh()->status);
    }

    public function test_late_failure_preserves_terminal_money_state_and_capture_evidence(): void
    {
        foreach (['paid', 'refunded', 'refund_pending', 'manual_reconciliation_required', 'duplicate_paid', 'paid_after_cancelled'] as $status) {
            [$booking, $installment, $transaction] = $this->payment();
            $booking->update(['status' => BookingStatus::Paid, 'paid_total' => 25]);
            $installment->update(['status' => InstallmentStatus::Paid, 'paid_amount' => 25]);
            $transaction->update(['status' => $status, 'provider_event_id' => 'evt_capture', 'payload' => ['capture_evidence' => true]]);
            $gateway = new FinancialRegressionStripeGateway($this->event($transaction, 'evt_failed_'.$transaction->id, 'failed'));
            $this->paymentWebhook($gateway);

            $this->assertSame($status, $transaction->fresh()->status);
            $this->assertSame('evt_capture', $transaction->fresh()->provider_event_id);
            $this->assertSame(['capture_evidence' => true], $transaction->fresh()->payload);
            $this->assertSame(InstallmentStatus::Paid, $installment->fresh()->status);
            $this->assertSame('25.00', $booking->fresh()->paid_total);
            $this->assertNull($booking->fresh()->auto_charge_failed_at);

            // The service must re-read state even if its caller holds a stale model.
            $transaction->status = 'pending';
            app(PaymentSettlementService::class)->markFailed($transaction, 'Late failure');
            $this->assertSame($status, $transaction->fresh()->status);
        }
    }

    public function test_failure_of_separate_pending_attempt_preserves_paid_installment(): void
    {
        [$booking, $installment, $transaction] = $this->payment();
        $booking->update(['status' => BookingStatus::Paid, 'paid_total' => 25]);
        $installment->update(['status' => InstallmentStatus::Paid, 'paid_amount' => 25]);
        app(PaymentSettlementService::class)->markFailed($transaction, 'Separate attempt failed');
        $this->assertSame('failed', $transaction->fresh()->status);
        $this->assertSame(InstallmentStatus::Paid, $installment->fresh()->status);
        $this->assertNull($booking->fresh()->auto_charge_failed_at);
    }

    public function test_pending_failure_still_records_failure(): void
    {
        [$booking, $installment, $transaction] = $this->payment();
        $this->paymentWebhook(new FinancialRegressionStripeGateway($this->event($transaction, 'evt_pending_failed', 'failed')));
        $this->assertSame('failed', $transaction->fresh()->status);
        $this->assertSame(InstallmentStatus::Failed, $installment->fresh()->status);
        $this->assertNotNull($booking->fresh()->auto_charge_failed_at);
    }

    public function test_second_capture_event_cannot_promote_pending_refund_reference_to_refunded(): void
    {
        [$booking, $installment, $transaction] = $this->payment();
        $booking->update(['status' => BookingStatus::Cancelled]);
        $installment->update(['status' => InstallmentStatus::Cancelled]);
        $gateway = new FinancialRegressionStripeGateway($this->event($transaction, 'evt_capture_one'));
        $this->paymentWebhook($gateway);
        $this->assertSame('refund_pending', $transaction->fresh()->status);
        $this->assertSame('re_pending', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.refund_reference'));

        $gateway->webhook = $this->event($transaction, 'evt_capture_two');
        $this->paymentWebhook($gateway);
        $this->assertSame('refund_pending', $transaction->fresh()->status);
        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame(2, PaymentGatewayWebhookEvent::query()->whereNull('processed_at')->count());

        $this->travel(6)->minutes();
        $gateway->refundStatus = 'succeeded';
        $this->paymentWebhook($gateway);
        $this->assertSame('refunded', $transaction->fresh()->status);
        $this->assertSame(0, PaymentGatewayWebhookEvent::query()->whereNull('processed_at')->count());
        $this->assertSame($gateway->refundKeys[0], $gateway->refundKeys[1]);
    }

    public function test_pending_stripe_refund_retrieves_current_provider_state_without_creating_again(): void
    {
        foreach (['pending' => 'refund_pending', 'succeeded' => 'refunded', 'failed' => 'manual_reconciliation_required'] as $providerStatus => $expectedStatus) {
            [, , $transaction] = $this->payment();
            $refundKey = 'stable-refund-'.$transaction->id;
            $transaction->update([
                'status' => 'refund_pending',
                'payload' => [
                    'payment_intent' => 'pi_provider_'.$transaction->id,
                    'late_terminal_reconciliation' => [
                        'refund_key' => $refundKey, 'refunded_amount' => 25, 'currency' => 'GBP',
                    ],
                ],
            ]);
            $gateway = new FinancialProviderSeamStripeGateway($providerStatus);
            $reconciler = app(LatePaymentRefundReconciler::class);

            $this->assertSame('pending', $reconciler->reconcile($transaction->id, $gateway));
            $this->assertSame('refund_pending', $transaction->fresh()->status);
            $this->assertSame('re_provider', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.refund_reference'));
            $this->assertCount(1, $gateway->createRequests);
            $this->assertSame($refundKey, $gateway->createRequests[0]['options']['idempotency_key']);
            $this->assertSame(2500, $gateway->createRequests[0]['parameters']['amount']);
            $this->assertSame([], $gateway->retrieveRequests);

            $this->travel(6)->minutes();
            $reconciler->reconcile($transaction->id, $gateway);

            $this->assertSame($expectedStatus, $transaction->fresh()->status);
            $this->assertSame($providerStatus, data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.refund_status'));
            $this->assertCount(1, $gateway->createRequests);
            $this->assertSame(['re_provider'], $gateway->retrieveRequests);
        }
    }

    public function test_either_wallet_switch_blocks_fundraising_api_and_direct_debit(): void
    {
        $user = $this->member();
        $wallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $user->id], ['currency' => 'GBP', 'balance' => 100]);
        $campaign = Campaign::query()->create([
            'slug' => 'security-campaign', 'title' => 'Security Campaign', 'goal_amount' => 1000,
            'cause' => 'Building', 'short_description' => 'Support the building project.', 'description' => 'Support the building project.',
            'currency' => 'GBP', 'status' => Campaign::STATUS_ACTIVE, 'start_at' => now()->subDay(), 'end_at' => now()->addMonth(),
        ]);
        $token = $user->issueApiToken();
        foreach ([[false, '1'], [true, '0']] as [$packageEnabled, $globalEnabled]) {
            config(['fundraising.wallet.enabled' => $packageEnabled]);
            AppSetting::query()->updateOrCreate(['key' => 'goshen_wallet_enabled'], ['value' => $globalEnabled, 'group' => 'goshen']);
            $this->assertFalse(app(CampaignService::class)->walletEnabled());
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/fundraising/campaigns/'.$campaign->slug.'/contribute', ['data' => ['amount' => 25]])
                ->assertStatus(422);
            $debit = app(FundraisingWalletGateway::class)->debitForFundraisingContribution($user, 25, $campaign, ['contribution_id' => 99]);
            $this->assertFalse($debit->success);
            $this->assertSame('wallet_disabled', $debit->errorCode);
            $this->assertSame('100.00', $wallet->fresh()->balance);
            $this->assertSame(0, CampaignContribution::query()->count());
            $this->assertSame(0, GoshenWalletLedgerEntry::query()->where('type', 'fundraising_payment')->count());
        }
    }

    public function test_completed_wallet_contribution_replay_remains_available_when_wallet_is_disabled(): void
    {
        config(['fundraising.wallet.enabled' => true]);
        AppSetting::query()->updateOrCreate(['key' => 'goshen_wallet_enabled'], ['value' => '1', 'group' => 'goshen']);
        $user = $this->member();
        $wallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $user->id], ['currency' => 'GBP', 'balance' => 100]);
        $campaign = Campaign::query()->create([
            'slug' => 'security-replay-campaign', 'title' => 'Replay Campaign', 'goal_amount' => 1000,
            'cause' => 'Building', 'short_description' => 'Support the building project.', 'description' => 'Support the building project.',
            'currency' => 'GBP', 'status' => Campaign::STATUS_ACTIVE, 'start_at' => now()->subDay(), 'end_at' => now()->addMonth(),
        ]);
        $this->withHeader('Authorization', 'Bearer '.$user->issueApiToken());
        $endpoint = '/api/fundraising/campaigns/'.$campaign->slug.'/contribute';
        $payload = ['data' => ['amount' => 25, 'idempotency_key' => 'completed-contribution-replay']];
        $this->postJson($endpoint, $payload)->assertOk()->assertJsonPath('idempotent_replay', false);

        foreach ([[false, '1'], [true, '0']] as [$packageEnabled, $globalEnabled]) {
            config(['fundraising.wallet.enabled' => $packageEnabled]);
            AppSetting::query()->updateOrCreate(['key' => 'goshen_wallet_enabled'], ['value' => $globalEnabled, 'group' => 'goshen']);
            $this->postJson($endpoint, $payload)->assertOk()->assertJsonPath('idempotent_replay', true);
            $this->assertSame('75.00', $wallet->fresh()->balance);
            $this->assertSame(1, CampaignContribution::query()->count());
            $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'fundraising_payment')->count());
        }
    }

    private function member(array $attributes = []): MobileUser
    {
        return MobileUser::query()->create(array_merge([
            'name' => 'Security Member', 'email' => str()->random(12).'@example.test', 'password' => 'secret',
            'login_type' => 'email', 'member_type' => 'church_member', 'is_verified' => true, 'email_verified_at' => now(),
        ], $attributes));
    }

    private function donation(): Donation
    {
        return Donation::query()->create([
            'reference' => 'giving_'.str()->random(12), 'provider' => 'stripe', 'status' => 'pending',
            'amount' => 25, 'currency' => 'GBP', 'name' => 'Giver', 'email' => 'giver@example.test',
        ]);
    }

    private function givingWebhook(Donation $donation, string $type, array $overrides, string $id): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_security_test']);
        $payload = json_encode(['id' => $id, 'object' => 'event', 'type' => $type, 'data' => ['object' => array_merge([
            'id' => 'cs_security', 'object' => 'checkout.session', 'client_reference_id' => $donation->reference,
            'payment_status' => 'paid', 'currency' => 'gbp', 'amount_total' => 2500,
        ], $overrides)]], JSON_THROW_ON_ERROR);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$payload, 'whsec_security_test');
        $request = Request::create('/webhooks/giving/stripe', 'POST', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$signature, 'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $this->assertSame(200, app(DonationStripeController::class)->webhook($request)->getStatusCode());
    }

    private function payment(): array
    {
        $event = Event::query()->create(['name' => 'Security Event', 'slug' => str()->random(12), 'type' => EventType::Sequential, 'timezone' => 'Europe/London', 'status' => 'published']);
        $booking = Booking::query()->create(['event_id' => $event->id, 'customer_email' => 'member@example.test', 'currency' => 'GBP', 'subtotal' => 25, 'total' => 25, 'paid_total' => 0, 'status' => BookingStatus::Pending]);
        $installment = PaymentInstallment::query()->create(['booking_id' => $booking->id, 'sequence' => 1, 'currency' => 'GBP', 'amount' => 25, 'paid_amount' => 0, 'due_on' => now()->toDateString(), 'status' => InstallmentStatus::Pending]);
        $transaction = PaymentTransaction::query()->create(['booking_id' => $booking->id, 'installment_id' => $installment->id, 'gateway' => 'stripe', 'provider_reference' => 'capture_'.str()->random(12), 'currency' => 'GBP', 'amount' => 25, 'status' => 'pending']);

        return [$booking, $installment, $transaction];
    }

    private function event(PaymentTransaction $transaction, string $id, string $status = 'paid'): VerifiedWebhook
    {
        return new VerifiedWebhook('stripe', $id, 'payment_intent.'.$status, $transaction->provider_reference, $status, 'GBP', 25, ['id' => $id, 'data' => ['object' => ['payment_intent' => 'pi_security']]]);
    }

    private function paymentWebhook(FinancialRegressionStripeGateway $gateway): void
    {
        app(PaymentWebhookController::class)('stripe', Request::create('/', 'POST'), new FinancialRegressionGatewayManager($gateway), app(PaymentSettlementService::class));
    }
}

class FinancialProviderSeamStripeGateway extends StripeGateway
{
    public array $createRequests = [];
    public array $retrieveRequests = [];

    public function __construct(private readonly string $currentRefundStatus) {}

    protected function createStripeRefund(array $parameters, array $options): array
    {
        $this->createRequests[] = ['parameters' => $parameters, 'options' => $options];

        return ['id' => 're_provider', 'status' => 'pending'];
    }

    protected function retrieveStripeRefund(string $reference): array
    {
        $this->retrieveRequests[] = $reference;

        return ['id' => $reference, 'status' => $this->currentRefundStatus];
    }
}

class FinancialRegressionGatewayManager extends PaymentGatewayManager
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function driver(string $gateway): PaymentGateway
    {
        return $this->gateway;
    }
}

class FinancialRegressionStripeGateway extends StripeGateway
{
    public int $refundCalls = 0;
    public array $refundKeys = [];
    public string $refundStatus = 'pending';

    public function __construct(public VerifiedWebhook $webhook) {}

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        return $this->webhook;
    }

    public function reusablePaymentDetails(array $payload): array
    {
        return ['payment_customer_id' => null, 'payment_method_id' => null];
    }

    public function refund(PaymentTransaction $transaction, float $amount, string $idempotencyKey): RefundResult
    {
        $this->refundCalls++;
        $this->refundKeys[] = $idempotencyKey;

        return new RefundResult('stripe', 're_pending', $this->refundStatus);
    }
}
