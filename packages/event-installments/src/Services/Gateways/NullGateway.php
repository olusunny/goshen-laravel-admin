<?php

namespace Personal\EventInstallments\Services\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;

class NullGateway implements PaymentGateway
{
    public function createCheckout(PaymentInstallment $installment): GatewayCheckout
    {
        return new GatewayCheckout(
            gateway: 'null',
            reference: 'null_'.Str::ulid(),
            checkoutUrl: null,
            payload: [
                'installment_id' => $installment->public_id,
                'amount' => (float) $installment->amount,
                'currency' => $installment->currency,
            ],
        );
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        return new VerifiedWebhook(
            gateway: 'null',
            eventId: (string) ($request->input('event_id') ?: Str::ulid()),
            eventType: (string) $request->input('type', 'payment.succeeded'),
            reference: $request->input('reference'),
            status: $request->input('status', 'paid'),
            currency: $request->input('currency'),
            amount: $request->filled('amount') ? (float) $request->input('amount') : null,
            payload: $request->all(),
        );
    }

    public function refund(PaymentTransaction $transaction, float $amount, string $idempotencyKey): RefundResult
    {
        return new RefundResult(
            gateway: 'null',
            reference: (string) ($transaction->provider_reference ?: $transaction->public_id),
            status: 'refunded',
            payload: ['amount' => $amount, 'idempotency_key' => $idempotencyKey],
        );
    }
}
