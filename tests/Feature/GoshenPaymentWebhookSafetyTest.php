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
use Tests\TestCase;

class GoshenPaymentWebhookSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_webhook_preserves_reusable_card_ids_but_never_enables_booking_auto_charge(): void
    {
        [$booking, $record, $transaction] = $this->pendingPayment();
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

    /** @return array{0: Booking, 1: PaymentInstallment, 2: PaymentTransaction} */
    private function pendingPayment(): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat Webhook Test',
            'slug' => 'goshen-webhook-' . str()->random(8),
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
            'provider_reference' => 'webhook_' . str()->random(10),
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
}

class FakeSafeGatewayManager extends PaymentGatewayManager
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    public function driver(string $gateway): PaymentGateway
    {
        return $this->gateway;
    }
}

class FakeSafeStripeGateway extends StripeGateway
{
    public int $refundCalls = 0;

    public function __construct(public VerifiedWebhook $webhook)
    {
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        return $this->webhook;
    }

    public function reusablePaymentDetails(array $payload): array
    {
        return ['payment_customer_id' => 'cus_safe', 'payment_method_id' => 'pm_safe'];
    }

    public function refund(PaymentTransaction $transaction, float $amount): RefundResult
    {
        $this->refundCalls++;

        return new RefundResult('stripe', 're_safe', 'refunded', ['amount' => $amount]);
    }
}
