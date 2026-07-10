<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentGatewayWebhookEvent;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\LatePaymentRefundReconciler;
use Personal\EventInstallments\Services\PaymentGatewayManager;
use Tests\TestCase;

class LatePaymentRefundReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_dispatch_lease_allows_only_one_provider_dispatch(): void
    {
        [$transaction, $event] = $this->pendingIntent();
        $service = app(LatePaymentRefundReconciler::class);
        $nestedGateway = new ReconciliationGateway(new RefundResult('paystack', 'refund_nested', 'processed'));
        $gateway = new ReconciliationGateway(new RefundResult('paystack', 'refund_primary', 'processed'));
        $gateway->duringRefund = function () use ($service, $transaction, $event, $nestedGateway): void {
            $this->assertSame('leased', $service->reconcile($transaction->id, $nestedGateway, $event->id));
        };

        $this->assertSame('refunded', $service->reconcile($transaction->id, $gateway, $event->id));

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame(0, $nestedGateway->refundCalls);
        $this->assertSame('refunded', $transaction->fresh()->status);
    }

    public function test_expired_dispatch_lease_is_recovered(): void
    {
        [$transaction, $event] = $this->pendingIntent([
            'dispatch_lease' => [
                'token' => 'crashed-worker',
                'claimed_at' => now()->subMinutes(10)->toIso8601String(),
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);
        $gateway = new ReconciliationGateway(new RefundResult('stripe', 're_recovered', 'succeeded'));

        $result = app(LatePaymentRefundReconciler::class)->reconcile($transaction->id, $gateway, $event->id);

        $this->assertSame('refunded', $result);
        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('re_recovered', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.refund_reference'));
    }

    public function test_pending_provider_status_stays_pending_until_later_terminal_reconciliation(): void
    {
        [$transaction, $event] = $this->pendingIntent();
        $service = app(LatePaymentRefundReconciler::class);

        $this->assertSame('pending', $service->reconcile(
            $transaction->id,
            new ReconciliationGateway(new RefundResult('paystack', 'refund_pending', 'pending')),
            $event->id,
        ));
        $this->assertSame('refund_pending', $transaction->fresh()->status);
        $this->assertSame('pending', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.refund_status'));
        $this->assertNull($event->fresh()->processed_at);

        $this->travel(6)->minutes();

        $this->assertSame('refunded', $service->reconcile(
            $transaction->id,
            new ReconciliationGateway(new RefundResult('paystack', 'refund_pending', 'processed')),
            $event->id,
        ));
        $this->assertSame('refunded', $transaction->fresh()->status);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_needs_attention_provider_status_requires_manual_reconciliation(): void
    {
        [$transaction, $event] = $this->pendingIntent();

        $result = app(LatePaymentRefundReconciler::class)->reconcile(
            $transaction->id,
            new ReconciliationGateway(new RefundResult('stripe', 're_attention', 'requires_action')),
            $event->id,
        );

        $this->assertSame('manual_reconciliation_required', $result);
        $this->assertSame('manual_reconciliation_required', $transaction->fresh()->status);
        $this->assertSame('requires_action', data_get($transaction->fresh()->payload, 'late_terminal_reconciliation.refund_status'));
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_scheduled_command_recovers_an_expired_pending_intent(): void
    {
        [$transaction] = $this->pendingIntent([
            'dispatch_lease' => [
                'token' => 'dead-command-worker',
                'claimed_at' => now()->subMinutes(10)->toIso8601String(),
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);
        $gateway = new ReconciliationGateway(new RefundResult('stripe', 're_command', 'succeeded'));
        $this->app->instance(PaymentGatewayManager::class, new ReconciliationGatewayManager($gateway));

        $this->artisan('goshen:reconcile-refund-pending --limit=10')->assertSuccessful();

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $transaction->fresh()->status);
    }

    /** @return array{0: PaymentTransaction, 1: PaymentGatewayWebhookEvent} */
    private function pendingIntent(array $overrides = []): array
    {
        $eventModel = Event::query()->create([
            'name' => 'Refund Reconciliation Test',
            'slug' => 'refund-reconciliation-'.str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
        $booking = Booking::query()->create([
            'event_id' => $eventModel->id,
            'customer_email' => str()->random(8).'@example.test',
            'currency' => 'NGN',
            'subtotal' => 250,
            'total' => 250,
            'paid_total' => 250,
            'status' => BookingStatus::Cancelled,
        ]);
        $record = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'NGN',
            'amount' => 250,
            'paid_amount' => 0,
            'due_on' => now()->toDateString(),
            'status' => InstallmentStatus::Cancelled,
        ]);
        $refundKey = 'refund_reconcile_'.str()->random(12);
        $reconciliation = array_merge([
            'action' => 'refund_pending',
            'refund_key' => $refundKey,
            'refunded_amount' => 250,
            'currency' => 'NGN',
            'provider_event_ids' => ['evt_reconcile'],
        ], $overrides);
        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $record->id,
            'gateway' => 'stripe',
            'provider_reference' => 'provider_'.str()->random(10),
            'provider_event_id' => 'evt_reconcile',
            'currency' => 'NGN',
            'amount' => 250,
            'status' => 'refund_pending',
            'payload' => [
                'payment_intent' => 'pi_reconcile',
                'late_terminal_reconciliation' => $reconciliation,
            ],
        ]);
        $webhookEvent = PaymentGatewayWebhookEvent::query()->create([
            'gateway' => 'stripe',
            'provider_event_id' => 'evt_reconcile',
            'event_type' => 'payment.succeeded',
            'payload' => [],
            'status' => 'refund_pending',
        ]);

        return [$transaction, $webhookEvent];
    }
}

class ReconciliationGateway implements PaymentGateway
{
    public int $refundCalls = 0;

    public ?\Closure $duringRefund = null;

    public function __construct(private readonly RefundResult $result) {}

    public function createCheckout(PaymentInstallment $installment): GatewayCheckout
    {
        throw new \RuntimeException('Not used.');
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        throw new \RuntimeException('Not used.');
    }

    public function refund(PaymentTransaction $transaction, float $amount, string $idempotencyKey): RefundResult
    {
        $this->refundCalls++;
        ($this->duringRefund) && ($this->duringRefund)();

        return $this->result;
    }
}

class ReconciliationGatewayManager extends PaymentGatewayManager
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function driver(string $gateway): PaymentGateway
    {
        return $this->gateway;
    }
}
