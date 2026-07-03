<?php

namespace Personal\EventInstallments\Services\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use RuntimeException;

class PaystackGateway implements PaymentGateway
{
    public function createCheckout(PaymentInstallment $installment): GatewayCheckout
    {
        $reference = 'ei_' . Str::ulid();
        $booking = $installment->booking;

        $response = Http::withToken($this->secret())
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $booking->customer_email,
                'amount' => (int) round(((float) $installment->amount) * 100),
                'currency' => $installment->currency,
                'reference' => $reference,
                'callback_url' => config('event-installments.payments.paystack.callback_url'),
                'metadata' => [
                    'installment_id' => $installment->public_id,
                    'booking_id' => $booking->public_id,
                ],
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack checkout initialization failed.');
        }

        $payload = $response->json();

        return new GatewayCheckout(
            gateway: 'paystack',
            reference: $reference,
            checkoutUrl: $payload['data']['authorization_url'] ?? null,
            payload: $payload,
        );
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        $secret = config('event-installments.payments.paystack.webhook_secret');
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Paystack-Signature');

        if (! is_string($secret) || $secret === '' || ! hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
            throw new InvalidArgumentException('Invalid Paystack webhook signature.');
        }

        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $data = $event['data'] ?? [];

        return new VerifiedWebhook(
            gateway: 'paystack',
            eventId: (string) ($data['id'] ?? Str::ulid()),
            eventType: (string) ($event['event'] ?? 'unknown'),
            reference: $data['reference'] ?? null,
            status: $data['status'] ?? null,
            currency: $data['currency'] ?? null,
            amount: isset($data['amount']) ? ((float) $data['amount']) / 100 : null,
            payload: $event,
        );
    }

    public function refund(PaymentTransaction $transaction, float $amount): RefundResult
    {
        $response = Http::withToken($this->secret())
            ->post('https://api.paystack.co/refund', [
                'transaction' => $transaction->provider_reference,
                'amount' => (int) round($amount * 100),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Paystack refund failed.');
        }

        return new RefundResult('paystack', (string) $transaction->provider_reference, 'refunded', $response->json());
    }

    private function secret(): string
    {
        $secret = config('event-installments.payments.paystack.secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('PAYSTACK_SECRET_KEY is not configured.');
        }

        return $secret;
    }
}
