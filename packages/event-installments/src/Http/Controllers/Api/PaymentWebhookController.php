<?php

namespace Personal\EventInstallments\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Models\PaymentGatewayWebhookEvent;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\PaymentInstallment;
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

            $transactionLookup = $webhook->reference
                ? PaymentTransaction::query()->where('gateway', $webhook->gateway)->where('provider_reference', $webhook->reference)->first()
                : null;
            $booking = $transactionLookup
                ? Booking::query()->whereKey($transactionLookup->booking_id)->lockForUpdate()->first()
                : null;
            if ($booking && $transactionLookup?->installment_id) {
                PaymentInstallment::query()
                    ->whereKey($transactionLookup->installment_id)
                    ->where('booking_id', $booking->id)
                    ->lockForUpdate()
                    ->first();
            }
            $transaction = $transactionLookup
                ? PaymentTransaction::query()->whereKey($transactionLookup->id)->lockForUpdate()->first()
                : null;

            if ($transaction && in_array($webhook->status, ['paid', 'succeeded', 'success'], true)) {
                if ($booking && $this->bookingIsTerminal($booking)) {
                    $this->reconcileLateSuccessfulPayment($transaction, $booking, $webhook, $paymentGateway);
                    $event->forceFill([
                        'status' => 'processed',
                        'processed_at' => now(),
                    ])->save();

                    return ['status' => 'processed'];
                }

                $transaction->forceFill([
                    'provider_event_id' => $webhook->eventId,
                    'payload' => $webhook->payload,
                ])->save();

                if ($paymentGateway instanceof StripeGateway) {
                    $billing = $paymentGateway->reusablePaymentDetails($webhook->payload);

                    $booking?->forceFill(array_filter([
                        'payment_customer_id' => $billing['payment_customer_id'],
                        'payment_method_id' => $billing['payment_method_id'],
                        'auto_charge_enabled' => false,
                    ], static fn ($value) => $value !== null))->save();
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

    private function bookingIsTerminal(Booking $booking): bool
    {
        $status = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);

        return in_array($status, [BookingStatus::Paid, BookingStatus::Cancelled, BookingStatus::Refunded], true);
    }

    private function reconcileLateSuccessfulPayment(
        PaymentTransaction $transaction,
        Booking $booking,
        VerifiedWebhook $webhook,
        PaymentGateway $paymentGateway,
    ): void {
        $transactionStatus = strtolower((string) $transaction->status);
        $payload = $transaction->payload ?: [];
        $reconciliation = is_array($payload['late_terminal_reconciliation'] ?? null)
            ? $payload['late_terminal_reconciliation']
            : [];
        $eventIds = collect($reconciliation['provider_event_ids'] ?? [])
            ->push($webhook->eventId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($transactionStatus === 'paid') {
            $transaction->forceFill([
                'payload' => array_merge($payload, [
                    'late_terminal_reconciliation' => array_merge($reconciliation, [
                        'action' => 'duplicate_notification_no_refund',
                        'booking_status' => $this->bookingStatusValue($booking),
                        'provider_event_ids' => $eventIds,
                        'last_seen_at' => now()->toIso8601String(),
                    ]),
                ]),
            ])->save();

            return;
        }

        if ($transactionStatus === 'refunded' || ($reconciliation['refund_reference'] ?? null)) {
            $transaction->forceFill([
                'status' => 'refunded',
                'provider_event_id' => $webhook->eventId,
                'payload' => array_merge($payload, [
                    'late_terminal_reconciliation' => array_merge($reconciliation, [
                        'provider_event_ids' => $eventIds,
                        'last_seen_at' => now()->toIso8601String(),
                    ]),
                ]),
            ])->save();

            return;
        }

        $transaction->forceFill([
            'provider_event_id' => $webhook->eventId,
            'payload' => $webhook->payload,
        ])->save();
        $amount = (float) ($webhook->amount ?? $transaction->amount);
        $refund = $paymentGateway->refund($transaction, $amount);

        $transaction->forceFill([
            'status' => 'refunded',
            'payload' => array_merge($webhook->payload, [
                'late_terminal_reconciliation' => [
                    'action' => 'automatic_refund',
                    'booking_status' => $this->bookingStatusValue($booking),
                    'provider_event_ids' => $eventIds,
                    'refund_reference' => $refund->reference,
                    'refund_status' => $refund->status,
                    'refund_payload' => $refund->payload,
                    'refunded_amount' => $amount,
                    'refunded_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }

    private function bookingStatusValue(Booking $booking): string
    {
        return $booking->status instanceof BookingStatus
            ? $booking->status->value
            : (string) $booking->status;
    }
}
