<?php

namespace Personal\EventInstallments\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Models\PaymentGatewayWebhookEvent;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\PaymentGatewayManager;
use Personal\EventInstallments\Services\Gateways\StripeGateway;
use Personal\EventInstallments\Services\PaymentSettlementService;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        string $gateway,
        Request $request,
        PaymentGatewayManager $gateways,
        PaymentSettlementService $settlements,
    )
    {
        $paymentGateway = $gateways->driver($gateway);
        $webhook = $paymentGateway->verifyWebhook($request);
        abort_unless($webhook->gateway === $gateway, 400, 'Gateway mismatch.');

        return DB::transaction(function () use ($webhook, $paymentGateway, $settlements) {
            $event = PaymentGatewayWebhookEvent::query()->firstOrCreate(
                [
                    'gateway' => $webhook->gateway,
                    'provider_event_id' => $webhook->eventId,
                ],
                [
                    'event_type' => $webhook->eventType,
                    'payload' => $webhook->payload,
                    'status' => 'received',
                ],
            );

            if ($event->processed_at) {
                return ['status' => 'duplicate'];
            }

            $transaction = $webhook->reference
                ? PaymentTransaction::query()->where('gateway', $webhook->gateway)->where('provider_reference', $webhook->reference)->first()
                : null;

            if ($transaction && in_array($webhook->status, ['paid', 'succeeded', 'success'], true)) {
                $transaction->forceFill([
                    'provider_event_id' => $webhook->eventId,
                    'payload' => $webhook->payload,
                ])->save();

                if ($paymentGateway instanceof StripeGateway) {
                    $billing = $paymentGateway->reusablePaymentDetails($webhook->payload);

                    if ($billing['payment_customer_id'] || $billing['payment_method_id']) {
                        $transaction->booking?->forceFill(array_filter([
                            'payment_customer_id' => $billing['payment_customer_id'],
                            'payment_method_id' => $billing['payment_method_id'],
                            'auto_charge_enabled' => false,
                        ], static fn ($value) => $value !== null))->save();
                    }
                }

                $settlements->markPaid($transaction, $webhook->amount, $webhook->currency);
            } elseif ($transaction && in_array($webhook->status, ['failed', 'cancelled', 'canceled'], true)) {
                $transaction->forceFill([
                    'provider_event_id' => $webhook->eventId,
                    'payload' => $webhook->payload,
                ])->save();

                $settlements->markFailed($transaction, 'Stripe reported that this payment was not completed.');
            }

            $event->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();

            return ['status' => 'processed'];
        });
    }
}
