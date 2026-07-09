<?php

namespace Personal\EventInstallments\Services\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use RuntimeException;
use Stripe\Exception\CardException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function createCheckout(PaymentInstallment $installment): GatewayCheckout
    {
        $reference = 'ei_' . Str::ulid();
        $installment->loadMissing('booking.event');
        $booking = $installment->booking;
        $metadata = $this->metadata($installment, $reference);
        $shouldSavePaymentMethod = $booking
            && $booking->installments()
                ->where('sequence', '>', (int) $installment->sequence)
                ->where('status', InstallmentStatus::Pending)
                ->exists();

        $paymentIntentData = ['metadata' => $metadata];

        if ($shouldSavePaymentMethod) {
            $paymentIntentData['setup_future_usage'] = 'off_session';
        }

        try {
            $session = $this->stripe()->checkout->sessions->create(array_filter([
                'mode' => 'payment',
                'success_url' => $this->requiredConfig('success_url', 'EVENT_INSTALLMENTS_STRIPE_SUCCESS_URL is not configured.'),
                'cancel_url' => $this->requiredConfig('cancel_url', 'EVENT_INSTALLMENTS_STRIPE_CANCEL_URL is not configured.'),
                'client_reference_id' => $reference,
                'customer' => $booking?->payment_customer_id,
                'customer_email' => $booking?->payment_customer_id ? null : $booking?->customer_email,
                'customer_creation' => $booking?->payment_customer_id ? null : 'always',
                'payment_method_types' => ['card'],
                'metadata' => $metadata,
                'payment_intent_data' => $paymentIntentData,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) $installment->currency),
                        'unit_amount' => $this->toMinorUnits((float) $installment->amount, (string) $installment->currency),
                        'product_data' => [
                            'name' => $this->productName($installment),
                        ],
                    ],
                ]],
            ], static fn ($value) => $value !== null), [
                'idempotency_key' => $reference,
            ]);
        } catch (ApiErrorException $exception) {
            throw new RuntimeException('Stripe checkout initialization failed.', 0, $exception);
        }

        $payload = $session->toArray();

        return new GatewayCheckout(
            gateway: 'stripe',
            reference: $reference,
            checkoutUrl: $payload['url'] ?? null,
            payload: $payload,
        );
    }

    public function chargeOffSession(PaymentInstallment $installment, ?string $reference = null): array
    {
        $installment->loadMissing('booking.event');
        $booking = $installment->booking;

        if (! $booking?->payment_customer_id || ! $booking?->payment_method_id) {
            throw new RuntimeException('A saved Stripe payment method is required before automatic installment charging can run.');
        }

        $reference ??= 'ei_auto_' . Str::ulid();
        $metadata = $this->metadata($installment, $reference);

        try {
            $intent = $this->stripe()->paymentIntents->create([
                'amount' => $this->toMinorUnits((float) $installment->amount, (string) $installment->currency),
                'currency' => strtolower((string) $installment->currency),
                'customer' => $booking->payment_customer_id,
                'payment_method' => $booking->payment_method_id,
                'off_session' => true,
                'confirm' => true,
                'metadata' => $metadata,
                'description' => $this->productName($installment),
            ], [
                'idempotency_key' => $reference,
            ]);
        } catch (CardException $exception) {
            return [
                'gateway' => 'stripe',
                'reference' => $reference,
                'status' => 'failed',
                'currency' => $installment->currency,
                'amount' => (float) $installment->amount,
                'payload' => [
                    'error' => [
                        'message' => $exception->getMessage(),
                        'decline_code' => $exception->getDeclineCode(),
                        'stripe_code' => $exception->getStripeCode(),
                    ],
                ],
            ];
        } catch (ApiErrorException $exception) {
            throw new RuntimeException('Stripe automatic installment charge failed.', 0, $exception);
        }

        $payload = $intent->toArray();

        return [
            'gateway' => 'stripe',
            'reference' => $reference,
            'status' => $payload['status'] === 'succeeded' ? 'paid' : (string) $payload['status'],
            'currency' => strtoupper((string) ($payload['currency'] ?? $installment->currency)),
            'amount' => isset($payload['amount_received'])
                ? ((float) $payload['amount_received']) / $this->minorUnitMultiplier((string) $installment->currency)
                : (float) $installment->amount,
            'payload' => $payload,
        ];
    }

    public function reusablePaymentDetails(array $payload): array
    {
        $object = data_get($payload, 'data.object', []);
        $customer = data_get($object, 'customer');
        $paymentIntent = data_get($object, 'payment_intent');
        $paymentMethod = data_get($object, 'payment_method');

        if (is_array($customer)) {
            $customer = $customer['id'] ?? null;
        }

        if (is_array($paymentIntent)) {
            $paymentMethod ??= $paymentIntent['payment_method'] ?? null;
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        if (is_array($paymentMethod)) {
            $paymentMethod = $paymentMethod['id'] ?? null;
        }

        if (! $paymentMethod && is_string($paymentIntent) && $paymentIntent !== '') {
            try {
                $intent = $this->stripe()->paymentIntents->retrieve($paymentIntent, []);
                $intentPayload = $intent->toArray();
                $paymentMethod = $intentPayload['payment_method'] ?? null;
                $customer ??= $intentPayload['customer'] ?? null;
            } catch (ApiErrorException) {
                // The webhook still settles the payment; saved method capture can be retried from Stripe later.
            }
        }

        if (is_array($customer)) {
            $customer = $customer['id'] ?? null;
        }

        if (is_array($paymentMethod)) {
            $paymentMethod = $paymentMethod['id'] ?? null;
        }

        return [
            'payment_customer_id' => is_string($customer) && $customer !== '' ? $customer : null,
            'payment_method_id' => is_string($paymentMethod) && $paymentMethod !== '' ? $paymentMethod : null,
        ];
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        $secret = $this->requiredConfig('webhook_secret', 'STRIPE_WEBHOOK_SECRET is not configured.');
        $signature = (string) $request->header('Stripe-Signature');
        $payload = $request->getContent();

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret, $this->webhookTolerance());
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            throw new InvalidArgumentException('Invalid Stripe webhook signature.', 0, $exception);
        }

        $eventPayload = $event->toArray();
        $object = $eventPayload['data']['object'] ?? [];
        $currency = isset($object['currency']) ? strtoupper((string) $object['currency']) : null;
        $amount = $this->webhookAmount($object, $currency);

        return new VerifiedWebhook(
            gateway: 'stripe',
            eventId: (string) $eventPayload['id'],
            eventType: (string) $eventPayload['type'],
            reference: $this->webhookReference($object),
            status: $this->webhookStatus((string) $eventPayload['type'], $object),
            currency: $currency,
            amount: $amount,
            payload: $eventPayload,
        );
    }

    public function refund(PaymentTransaction $transaction, float $amount): RefundResult
    {
        $paymentIntent = $this->paymentIntentFromTransaction($transaction);

        try {
            $refund = $this->stripe()->refunds->create([
                'payment_intent' => $paymentIntent,
                'amount' => $this->toMinorUnits($amount, (string) $transaction->currency),
            ]);
        } catch (ApiErrorException $exception) {
            throw new RuntimeException('Stripe refund failed.', 0, $exception);
        }

        return new RefundResult('stripe', (string) $refund->id, (string) $refund->status, $refund->toArray());
    }

    private function metadata(PaymentInstallment $installment, string $reference): array
    {
        $booking = $installment->booking;
        $event = $booking?->event;

        return array_filter([
            'event_installments_reference' => $reference,
            'booking_id' => $booking?->public_id,
            'installment_id' => $installment->public_id,
            'installment_sequence' => (string) $installment->sequence,
            'event_id' => $event?->public_id,
            'source' => 'event-installments-laravel',
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function productName(PaymentInstallment $installment): string
    {
        $metadata = is_array($installment->metadata) ? $installment->metadata : [];
        $label = trim((string) ($metadata['label'] ?? ''));
        if ($label !== '') {
            return ($installment->booking?->event?->name ?: 'Event booking') . ' - ' . $label;
        }

        $booking = $installment->booking;
        $eventName = $booking?->event?->name ?: 'Event booking';
        $totalAmount = (float) ($booking?->total_amount ?? 0);
        $installmentAmount = (float) $installment->amount;
        $isFullPayment = $totalAmount > 0 && $installmentAmount + 0.01 >= $totalAmount;

        return $eventName . ($isFullPayment
            ? ' - full payment'
            : ' - installment #' . $installment->sequence);
    }

    private function webhookReference(array $object): ?string
    {
        return $object['client_reference_id']
            ?? $object['metadata']['event_installments_reference']
            ?? null;
    }

    private function webhookStatus(string $eventType, array $object): ?string
    {
        return match ($eventType) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'payment_intent.succeeded' => 'paid',
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed' => 'failed',
            'checkout.session.expired',
            'payment_intent.canceled' => 'cancelled',
            default => $object['payment_status'] ?? $object['status'] ?? null,
        };
    }

    private function webhookAmount(array $object, ?string $currency): ?float
    {
        $minorAmount = $object['amount_total'] ?? $object['amount_received'] ?? null;

        if ($minorAmount === null || $currency === null) {
            return null;
        }

        return ((float) $minorAmount) / $this->minorUnitMultiplier($currency);
    }

    private function paymentIntentFromTransaction(PaymentTransaction $transaction): string
    {
        $paymentIntent = data_get($transaction->payload, 'data.object.payment_intent')
            ?? data_get($transaction->payload, 'payment_intent');

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        if (! is_string($paymentIntent) || $paymentIntent === '') {
            throw new RuntimeException('Stripe payment intent is missing from the transaction payload.');
        }

        return $paymentIntent;
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        return (int) round($amount * $this->minorUnitMultiplier($currency));
    }

    private function minorUnitMultiplier(string $currency): int
    {
        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF',
            'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 1 : 100;
    }

    private function stripe(): StripeClient
    {
        return new StripeClient([
            'api_key' => $this->secret(),
            'stripe_version' => $this->apiVersion(),
        ]);
    }

    private function secret(): string
    {
        return $this->requiredConfig('secret', 'STRIPE_SECRET is not configured.');
    }

    private function apiVersion(): string
    {
        return $this->requiredConfig('api_version', 'STRIPE_API_VERSION is not configured.');
    }

    private function webhookTolerance(): int
    {
        return max(0, (int) config('event-installments.payments.stripe.webhook_tolerance', 300));
    }

    private function requiredConfig(string $key, string $message): string
    {
        $value = config('event-installments.payments.stripe.' . $key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException($message);
        }

        return $value;
    }
}
