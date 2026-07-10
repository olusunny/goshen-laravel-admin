<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\Gateways\PaystackGateway;
use Personal\EventInstallments\Services\Gateways\StripeGateway;
use Tests\TestCase;

class EventInstallmentsGatewayRefundTest extends TestCase
{
    public function test_stripe_receives_the_stable_refund_idempotency_key(): void
    {
        $transaction = new PaymentTransaction([
            'gateway' => 'stripe',
            'provider_reference' => 'ei_reference_refund',
            'currency' => 'USD',
            'amount' => 20,
            'payload' => ['payment_intent' => 'pi_refund'],
        ]);
        $gateway = new CapturingApplicationStripeRefundGateway;

        $gateway->refund($transaction, 10, 'stable-refund-key');

        $this->assertSame('stable-refund-key', $gateway->options['idempotency_key']);
        $this->assertSame('pi_refund', $gateway->parameters['payment_intent']);
        $this->assertSame(1000, $gateway->parameters['amount']);
    }

    public function test_paystack_reuses_matching_refund_without_posting_again(): void
    {
        config(['event-installments.payments.paystack.secret' => 'sk_test_paystack']);
        $transaction = $this->paystackTransaction();
        $key = 'stable-paystack-key';
        Http::fake([
            'api.paystack.co/refund*' => Http::response([
                'status' => true,
                'data' => [[
                    'id' => 901,
                    'status' => 'pending',
                    'amount' => 25000,
                    'merchant_note' => 'Goshen refund '.$key,
                    'transaction' => ['reference' => $transaction->provider_reference],
                ]],
            ]),
        ]);

        $result = (new PaystackGateway)->refund($transaction, 250, $key);

        $this->assertSame('901', $result->reference);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
    }

    public function test_paystack_requeries_after_create_error_before_retrying(): void
    {
        config(['event-installments.payments.paystack.secret' => 'sk_test_paystack']);
        $transaction = $this->paystackTransaction();
        $key = 'stable-paystack-retry-key';
        Http::fakeSequence('api.paystack.co/refund*')
            ->push(['status' => true, 'data' => []])
            ->push(['status' => false, 'message' => 'timeout after create'], 500)
            ->push([
                'status' => true,
                'data' => [[
                    'id' => 902,
                    'status' => 'processed',
                    'amount' => 25000,
                    'merchant_note' => 'Goshen refund '.$key,
                    'transaction' => ['reference' => $transaction->provider_reference],
                ]],
            ]);

        $result = (new PaystackGateway)->refund($transaction, 250, $key);

        $this->assertSame('902', $result->reference);
        Http::assertSentCount(3);
    }

    private function paystackTransaction(): PaymentTransaction
    {
        return new PaymentTransaction([
            'gateway' => 'paystack',
            'provider_reference' => 'paystack_transaction_reference',
            'currency' => 'NGN',
            'amount' => 250,
            'payload' => [],
        ]);
    }
}

class CapturingApplicationStripeRefundGateway extends StripeGateway
{
    public array $parameters = [];

    public array $options = [];

    protected function createStripeRefund(array $parameters, array $options): array
    {
        $this->parameters = $parameters;
        $this->options = $options;

        return ['id' => 're_test', 'status' => 'succeeded'];
    }
}
