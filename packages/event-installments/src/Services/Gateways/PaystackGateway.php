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
        $reference = 'ei_'.Str::ulid();
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

    public function refund(PaymentTransaction $transaction, float $amount, string $idempotencyKey): RefundResult
    {
        $minorAmount = (int) round($amount * 100);
        $merchantNote = 'Goshen refund '.$idempotencyKey;

        if ($existing = $this->existingRefund($transaction, $minorAmount, $merchantNote)) {
            return $existing;
        }

        try {
            $response = Http::withToken($this->secret())
                ->post('https://api.paystack.co/refund', [
                    'transaction' => $transaction->provider_reference,
                    'amount' => $minorAmount,
                    'merchant_note' => $merchantNote,
                ]);
        } catch (\Throwable $exception) {
            if ($existing = $this->existingRefund($transaction, $minorAmount, $merchantNote)) {
                return $existing;
            }

            throw new RuntimeException('Paystack refund failed.', 0, $exception);
        }

        if (! $response->successful() || ! $response->json('status')) {
            if ($existing = $this->existingRefund($transaction, $minorAmount, $merchantNote)) {
                return $existing;
            }

            throw new RuntimeException('Paystack refund failed.');
        }

        return $this->refundResult($response->json('data', []), $response->json());
    }

    private function existingRefund(PaymentTransaction $transaction, int $minorAmount, string $merchantNote): ?RefundResult
    {
        try {
            $response = Http::withToken($this->secret())
                ->get('https://api.paystack.co/refund', ['transaction' => $transaction->provider_reference]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful() || ! $response->json('status')) {
            return null;
        }

        $refunds = $response->json('data', []);
        if (! is_array($refunds)) {
            return null;
        }

        foreach ($refunds as $refund) {
            if (! is_array($refund)) {
                continue;
            }

            $refundTransaction = data_get($refund, 'transaction.reference') ?? data_get($refund, 'transaction');
            $providerTransactionId = data_get($transaction->payload, 'data.id')
                ?? data_get($transaction->payload, 'data.object.id');
            $matchesTransaction = (string) $refundTransaction === (string) $transaction->provider_reference
                || ($providerTransactionId !== null && (string) $refundTransaction === (string) $providerTransactionId);
            $status = strtolower((string) ($refund['status'] ?? ''));
            if ($matchesTransaction
                && (int) ($refund['amount'] ?? 0) === $minorAmount
                && hash_equals($merchantNote, (string) ($refund['merchant_note'] ?? ''))
                && ! in_array($status, ['failed', 'abandoned', 'declined'], true)) {
                return $this->refundResult($refund, $response->json());
            }
        }

        return null;
    }

    private function refundResult(array $refund, array $payload): RefundResult
    {
        return new RefundResult(
            'paystack',
            (string) ($refund['id'] ?? $refund['reference'] ?? ''),
            (string) ($refund['status'] ?? 'pending'),
            $payload,
        );
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
