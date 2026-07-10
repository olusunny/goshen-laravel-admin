<?php

namespace Tests\Feature;

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
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\Gateways\StripeGateway;
use Personal\EventInstallments\Services\PaymentGatewayManager;
use Personal\EventInstallments\Services\PaymentSettlementService;
use RuntimeException;
use Tests\TestCase;

class GoshenPaymentWebhookSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_webhook_preserves_reusable_card_ids_but_never_enables_booking_auto_charge(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['auto_charge_enabled' => true])->save();
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_paid'));

        $result = app(PaymentWebhookController::class)(
            'stripe',
            Request::create('/webhooks/event-installments/stripe', 'POST'),
            new FakeSafeGatewayManager($gateway),
            app(PaymentSettlementService::class),
        );

        $this->assertSame(['status' => 'processed'], $result);
        $booking->refresh();
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame('250.00', $booking->paid_total);
        $this->assertFalse($booking->auto_charge_enabled);
        $this->assertSame('cus_safe', $booking->payment_customer_id);
        $this->assertSame('pm_safe', $booking->payment_method_id);
        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame('250.00', $record->fresh()->paid_amount);
        $this->assertSame(0, $gateway->refundCalls);
    }

    public function test_new_late_charge_on_terminal_booking_is_refunded_once_across_distinct_webhook_events(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['status' => BookingStatus::Paid, 'paid_total' => 250])->save();
        $record->forceFill(['status' => InstallmentStatus::Paid, 'paid_amount' => 250, 'paid_at' => now()])->save();
        $this->canonicalPaidTransaction($booking, $record);
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_late_1'));
        $controller = app(PaymentWebhookController::class);
        $manager = new FakeSafeGatewayManager($gateway);

        $controller('stripe', Request::create('/', 'POST'), $manager, app(PaymentSettlementService::class));

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $transaction->fresh()->status);
        $this->assertSame('automatic_refund', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.action'));
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);

        $gateway->webhook = $this->paidWebhook($transaction->fresh(), 'evt_late_2');
        $controller('stripe', Request::create('/', 'POST'), $manager, app(PaymentSettlementService::class));

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertCount(2, data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.provider_event_ids'));
    }

    public function test_duplicate_notification_for_the_legitimate_paid_transaction_is_not_refunded(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['status' => BookingStatus::Paid, 'paid_total' => 250])->save();
        $record->forceFill(['status' => InstallmentStatus::Paid, 'paid_amount' => 250, 'paid_at' => now()])->save();
        $transaction->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_duplicate_paid'));

        app(PaymentWebhookController::class)(
            'stripe', Request::create('/', 'POST'), new FakeSafeGatewayManager($gateway), app(PaymentSettlementService::class),
        );

        $this->assertSame(0, $gateway->refundCalls);
        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame('duplicate_notification_no_refund', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.action'));
    }

    public function test_failed_late_refund_rolls_back_webhook_and_transaction_for_retry(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();
        $record->forceFill(['status' => InstallmentStatus::Cancelled])->save();
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_refund_retry'));
        $gateway->failRefund = true;

        try {
            app(PaymentWebhookController::class)(
                'stripe', Request::create('/', 'POST'), new FakeSafeGatewayManager($gateway), app(PaymentSettlementService::class),
            );
            $this->fail('Expected the failed refund to leave the webhook retryable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('refund failed', $exception->getMessage());
        }

        $this->assertSame('refund_pending', $transaction->fresh()->status);
        $this->assertSame('refund_pending', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.action'));
        $this->assertDatabaseHas('ei_payment_gateway_webhook_events', [
            'gateway' => 'stripe',
            'provider_event_id' => 'evt_refund_retry',
            'status' => 'refund_pending',
        ]);
        $this->assertSame(1, $gateway->refundCalls);
    }

    public function test_paid_booking_without_a_distinct_canonical_capture_requires_manual_reconciliation(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['status' => BookingStatus::Paid, 'paid_total' => 250])->save();
        $record->forceFill(['status' => InstallmentStatus::Paid, 'paid_amount' => 250, 'paid_at' => now()])->save();
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_ambiguous_paid'));

        app(PaymentWebhookController::class)(
            'stripe', Request::create('/', 'POST'), new FakeSafeGatewayManager($gateway), app(PaymentSettlementService::class),
        );

        $this->assertSame(0, $gateway->refundCalls);
        $this->assertSame('manual_reconciliation_required', $transaction->fresh()->status);
        $this->assertSame('manual_reconciliation_required', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.action'));
    }

    public function test_expired_second_capture_with_canonical_paid_transaction_is_refunded(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['status' => BookingStatus::Paid, 'paid_total' => 250])->save();
        $record->forceFill(['status' => InstallmentStatus::Paid, 'paid_amount' => 250, 'paid_at' => now()])->save();
        $transaction->forceFill(['status' => 'expired'])->save();
        $this->canonicalPaidTransaction($booking, $record);
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_expired_second_capture'));

        app(PaymentWebhookController::class)(
            'stripe', Request::create('/', 'POST'), new FakeSafeGatewayManager($gateway), app(PaymentSettlementService::class),
        );

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $transaction->fresh()->status);
    }

    public function test_pending_capture_on_refunded_booking_is_refunded(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['status' => BookingStatus::Refunded])->save();
        $record->forceFill(['status' => InstallmentStatus::Refunded])->save();
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_refunded_booking_capture'));

        app(PaymentWebhookController::class)(
            'stripe', Request::create('/', 'POST'), new FakeSafeGatewayManager($gateway), app(PaymentSettlementService::class),
        );

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $transaction->fresh()->status);
    }

    public function test_sole_legacy_duplicate_status_is_never_automatically_refunded(): void
    {
        foreach (['duplicate_paid', 'paid_after_cancelled'] as $index => $legacyStatus) {
            [$booking, $record, $transaction] = $this->pendingPayment();
            $booking->forceFill(['status' => BookingStatus::Paid, 'paid_total' => 250])->save();
            $record->forceFill(['status' => InstallmentStatus::Paid, 'paid_amount' => 250, 'paid_at' => now()])->save();
            $transaction->forceFill(['status' => $legacyStatus])->save();
            $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_legacy_'.$index));

            app(PaymentWebhookController::class)(
                'stripe', Request::create('/', 'POST'), new FakeSafeGatewayManager($gateway), app(PaymentSettlementService::class),
            );

            $this->assertSame(0, $gateway->refundCalls);
            $this->assertSame('manual_reconciliation_required', $transaction->fresh()->status);
        }
    }

    public function test_provider_success_then_local_confirmation_failure_retries_with_same_refund_key(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
        $booking->forceFill(['status' => BookingStatus::Cancelled])->save();
        $record->forceFill(['status' => InstallmentStatus::Cancelled])->save();
        $gateway = new FakeSafeStripeGateway($this->paidWebhook($transaction, 'evt_crash_safe'));
        $controller = new CrashAfterRemoteRefundController;
        $manager = new FakeSafeGatewayManager($gateway);

        try {
            $controller('stripe', Request::create('/', 'POST'), $manager, app(PaymentSettlementService::class));
            $this->fail('Expected simulated local persistence failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('confirmation crash', $exception->getMessage());
        }

        $this->assertSame('refund_pending', $transaction->fresh()->status);
        $controller('stripe', Request::create('/', 'POST'), $manager, app(PaymentSettlementService::class));

        $this->assertSame(2, $gateway->refundRequests);
        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $transaction->fresh()->status);
    }

    /** @return array{0: Booking, 1: PaymentInstallment, 2: PaymentTransaction} */
    private function pendingPayment(): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat Webhook Test',
            'slug' => 'goshen-webhook-'.str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_email' => 'webhook@example.test',
            'currency' => 'NGN',
            'subtotal' => 250,
            'total' => 250,
            'paid_total' => 0,
            'status' => BookingStatus::Pending,
            'auto_charge_enabled' => false,
        ]);
        $record = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'NGN',
            'amount' => 250,
            'paid_amount' => 0,
            'due_on' => now()->toDateString(),
            'status' => InstallmentStatus::Pending,
        ]);
        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $record->id,
            'gateway' => 'stripe',
            'provider_reference' => 'webhook_'.str()->random(10),
            'currency' => 'NGN',
            'amount' => 250,
            'status' => 'pending',
            'payload' => ['url' => 'https://checkout.test/session'],
        ]);

        return [$booking, $record, $transaction];
    }

    private function paidWebhook(PaymentTransaction $transaction, string $eventId): VerifiedWebhook
    {
        return new VerifiedWebhook(
            gateway: 'stripe',
            eventId: $eventId,
            eventType: 'checkout.session.completed',
            reference: $transaction->provider_reference,
            status: 'paid',
            currency: 'NGN',
            amount: 250,
            payload: [
                'id' => $eventId,
                'data' => ['object' => [
                    'payment_intent' => 'pi_safe',
                    'customer' => 'cus_safe',
                    'payment_method' => 'pm_safe',
                ]],
            ],
        );
    }

    private function canonicalPaidTransaction(Booking $booking, PaymentInstallment $record): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $record->id,
            'gateway' => 'stripe',
            'provider_reference' => 'canonical_'.str()->random(10),
            'currency' => 'NGN',
            'amount' => 250,
            'status' => 'paid',
            'paid_at' => now(),
            'payload' => ['payment_intent' => 'pi_canonical'],
        ]);
    }
}

class FakeSafeGatewayManager extends PaymentGatewayManager
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function driver(string $gateway): PaymentGateway
    {
        return $this->gateway;
    }
}

class FakeSafeStripeGateway extends StripeGateway
{
    public int $refundCalls = 0;

    public int $refundRequests = 0;

    public bool $failRefund = false;

    private array $refundsByKey = [];

    public function __construct(public VerifiedWebhook $webhook) {}

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        return $this->webhook;
    }

    public function reusablePaymentDetails(array $payload): array
    {
        return ['payment_customer_id' => 'cus_safe', 'payment_method_id' => 'pm_safe'];
    }

    public function refund(PaymentTransaction $transaction, float $amount, string $idempotencyKey): RefundResult
    {
        $this->refundRequests++;

        if (isset($this->refundsByKey[$idempotencyKey])) {
            return $this->refundsByKey[$idempotencyKey];
        }

        $this->refundCalls++;

        if ($this->failRefund) {
            throw new RuntimeException('Fake refund failed.');
        }

        return $this->refundsByKey[$idempotencyKey] = new RefundResult('stripe', 're_safe', 'refunded', [
            'amount' => $amount,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}

class CrashAfterRemoteRefundController extends PaymentWebhookController
{
    private bool $crash = true;

    protected function persistRefundResult(int $transactionId, int $eventId, string $refundKey, RefundResult $refund): void
    {
        if ($this->crash) {
            $this->crash = false;
            throw new RuntimeException('Simulated confirmation crash.');
        }

        parent::persistRefundResult($transactionId, $eventId, $refundKey, $refund);
    }
}
