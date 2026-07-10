<?php

namespace Personal\EventInstallments\Tests\Unit;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\Gateways\StripeGateway;
use Personal\EventInstallments\Tests\TestCase;
use RuntimeException;

class StripeGatewayTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('event-installments.payments.stripe.secret', 'sk_test_unit');
        $app['config']->set('event-installments.payments.stripe.webhook_secret', 'whsec_unit');
        $app['config']->set('event-installments.payments.stripe.webhook_tolerance', 300);
    }

    public function test_it_verifies_checkout_webhooks_and_resolves_internal_reference(): void
    {
        $payload = $this->stripeEventPayload([
            'id' => 'evt_checkout_paid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'client_reference_id' => 'ei_reference_123',
                    'payment_status' => 'paid',
                    'currency' => 'usd',
                    'amount_total' => 12500,
                    'payment_intent' => 'pi_test_123',
                ],
            ],
        ]);

        $webhook = (new StripeGateway())->verifyWebhook($this->signedRequest($payload));

        $this->assertSame('stripe', $webhook->gateway);
        $this->assertSame('evt_checkout_paid', $webhook->eventId);
        $this->assertSame('ei_reference_123', $webhook->reference);
        $this->assertSame('paid', $webhook->status);
        $this->assertSame('USD', $webhook->currency);
        $this->assertSame(125.0, $webhook->amount);
    }

    public function test_it_uses_payment_intent_metadata_for_non_checkout_events(): void
    {
        $payload = $this->stripeEventPayload([
            'id' => 'evt_pi_paid',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_456',
                    'status' => 'succeeded',
                    'currency' => 'usd',
                    'amount_received' => 5000,
                    'metadata' => [
                        'event_installments_reference' => 'ei_reference_456',
                    ],
                ],
            ],
        ]);

        $webhook = (new StripeGateway())->verifyWebhook($this->signedRequest($payload));

        $this->assertSame('ei_reference_456', $webhook->reference);
        $this->assertSame('paid', $webhook->status);
        $this->assertSame(50.0, $webhook->amount);
    }

    public function test_it_rejects_stale_webhook_signatures(): void
    {
        $payload = $this->stripeEventPayload([
            'id' => 'evt_old',
            'type' => 'checkout.session.completed',
            'data' => ['object' => []],
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new StripeGateway())->verifyWebhook($this->signedRequest($payload, now()->subMinutes(10)->timestamp));
    }

    public function test_it_refuses_refunds_without_a_stripe_payment_intent(): void
    {
        $transaction = new PaymentTransaction([
            'gateway' => 'stripe',
            'provider_reference' => 'ei_reference_without_payment_intent',
            'currency' => 'USD',
            'amount' => 20,
            'payload' => [
                'id' => 'cs_test_without_payment_intent',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stripe payment intent is missing');

        (new StripeGateway())->refund($transaction, 10, 'refund-key-without-intent');
    }

    private function stripeEventPayload(array $event): string
    {
        return json_encode($event, JSON_THROW_ON_ERROR);
    }

    private function signedRequest(string $payload, ?int $timestamp = null): Request
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_unit');

        return Request::create(
            '/webhooks/event-installments/stripe',
            'POST',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=' . $signature],
            $payload,
        );
    }
}
