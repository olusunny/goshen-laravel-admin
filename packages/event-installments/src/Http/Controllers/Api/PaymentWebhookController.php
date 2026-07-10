<?php

namespace Personal\EventInstallments\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\PaymentGatewayWebhookEvent;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\Gateways\StripeGateway;
use Personal\EventInstallments\Services\LatePaymentRefundReconciler;
use Personal\EventInstallments\Services\PaymentGatewayManager;
use Personal\EventInstallments\Services\PaymentSettlementService;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        string $gateway,
        Request $request,
        PaymentGatewayManager $gateways,
        PaymentSettlementService $settlements,
    ) {
        $paymentGateway = $gateways->driver($gateway);
        $webhook = $paymentGateway->verifyWebhook($request);
        abort_unless($webhook->gateway === $gateway, 400, 'Gateway mismatch.');

        $outcome = DB::transaction(function () use ($webhook, $paymentGateway, $settlements): array {
            $createdEvent = PaymentGatewayWebhookEvent::query()->firstOrCreate(
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
            $event = PaymentGatewayWebhookEvent::query()->whereKey($createdEvent->id)->lockForUpdate()->firstOrFail();

            if ($event->processed_at) {
                return ['response' => ['status' => 'duplicate']];
            }

            $transactionLookup = $webhook->reference
                ? PaymentTransaction::query()
                    ->where('gateway', $webhook->gateway)
                    ->where('provider_reference', $webhook->reference)
                    ->first()
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
                    $refundIntent = $this->prepareLateSuccessfulPayment($transaction, $booking, $webhook);

                    if ($refundIntent !== null) {
                        $event->forceFill(['status' => 'refund_pending'])->save();

                        return $refundIntent + ['event_id' => $event->id];
                    }

                    $event->forceFill(['status' => 'processed', 'processed_at' => now()])->save();

                    return ['response' => ['status' => 'processed']];
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

                $settlements->markFailed($transaction, 'The payment gateway reported that this payment was not completed.');
            }

            $event->forceFill(['status' => 'processed', 'processed_at' => now()])->save();

            return ['response' => ['status' => 'processed']];
        });

        if (isset($outcome['response'])) {
            return $outcome['response'];
        }

        app(LatePaymentRefundReconciler::class)->reconcile(
            $outcome['transaction_id'],
            $paymentGateway,
            $outcome['event_id'],
        );

        return ['status' => 'processed'];
    }

    private function bookingIsTerminal(Booking $booking): bool
    {
        $status = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);

        return in_array($status, [BookingStatus::Paid, BookingStatus::Cancelled, BookingStatus::Refunded], true);
    }

    private function prepareLateSuccessfulPayment(
        PaymentTransaction $transaction,
        Booking $booking,
        VerifiedWebhook $webhook,
    ): ?array {
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
        $reconciliation['booking_status'] = $this->bookingStatusValue($booking);
        $reconciliation['previous_transaction_status'] ??= $transactionStatus;

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

            return null;
        }

        if ($transactionStatus === 'refund_pending' && filled($reconciliation['refund_key'] ?? null)) {
            $transaction->forceFill([
                'provider_event_id' => $webhook->eventId,
                'payload' => array_merge($payload, [
                    'late_terminal_reconciliation' => array_merge($reconciliation, [
                        'provider_event_ids' => $eventIds,
                        'last_seen_at' => now()->toIso8601String(),
                    ]),
                ]),
            ])->save();

            return [
                'transaction_id' => $transaction->id,
                'amount' => (float) $reconciliation['refunded_amount'],
                'refund_key' => (string) $reconciliation['refund_key'],
            ];
        }

        if ($transactionStatus === 'paid') {
            $this->markReconciliation($transaction, $webhook, $payload, $reconciliation, $eventIds, 'duplicate_notification_no_refund');

            return null;
        }

        if ($transactionStatus === 'duplicate_paid' || str_starts_with($transactionStatus, 'paid_after_')) {
            $this->markReconciliation($transaction, $webhook, $payload, $reconciliation, $eventIds, 'manual_reconciliation_required', true);

            return null;
        }

        $bookingStatus = $this->bookingStatusValue($booking);
        $isFreshCaptureState = in_array($transactionStatus, ['pending', 'expired'], true);
        $hasSeparateCanonicalCapture = PaymentTransaction::query()
            ->where('booking_id', $booking->id)
            ->whereKeyNot($transaction->id)
            ->where('status', 'paid')
            ->exists();
        $hasPositiveDistinctCaptureEvidence = $isFreshCaptureState
            && (in_array($bookingStatus, [BookingStatus::Cancelled->value, BookingStatus::Refunded->value], true)
                || ($bookingStatus === BookingStatus::Paid->value && $hasSeparateCanonicalCapture));

        if (! $hasPositiveDistinctCaptureEvidence) {
            $this->markReconciliation($transaction, $webhook, $payload, $reconciliation, $eventIds, 'manual_reconciliation_required', true);

            return null;
        }

        $amount = (float) ($webhook->amount ?? $transaction->amount);
        $refundKey = (string) ($reconciliation['refund_key'] ?? $this->refundKey($transaction, $amount));
        $requestedAt = (string) ($reconciliation['refund_requested_at'] ?? now()->toIso8601String());

        $transaction->forceFill([
            'status' => 'refund_pending',
            'provider_event_id' => $webhook->eventId,
            'payload' => array_merge($webhook->payload, [
                'late_terminal_reconciliation' => array_merge($reconciliation, [
                    'action' => 'refund_pending',
                    'booking_status' => $bookingStatus,
                    'provider_event_ids' => $eventIds,
                    'refund_key' => $refundKey,
                    'refund_requested_at' => $requestedAt,
                    'refunded_amount' => $amount,
                    'currency' => strtoupper((string) $transaction->currency),
                ]),
            ]),
        ])->save();

        return [
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'refund_key' => $refundKey,
        ];
    }

    private function markReconciliation(
        PaymentTransaction $transaction,
        VerifiedWebhook $webhook,
        array $payload,
        array $reconciliation,
        array $eventIds,
        string $action,
        bool $manual = false,
    ): void {
        $transaction->forceFill([
            'status' => $manual ? 'manual_reconciliation_required' : $transaction->status,
            'provider_event_id' => $webhook->eventId,
            'payload' => array_merge($payload, [
                'late_terminal_reconciliation' => array_merge($reconciliation, [
                    'action' => $action,
                    'provider_event_ids' => $eventIds,
                    'last_seen_at' => now()->toIso8601String(),
                    'capture_payload' => $webhook->payload,
                ]),
            ]),
        ])->save();
    }

    private function refundKey(PaymentTransaction $transaction, float $amount): string
    {
        return 'goshen_refund_'.hash('sha256', implode('|', [
            $transaction->gateway,
            $transaction->public_id ?: $transaction->id,
            number_format($amount, 2, '.', ''),
            strtoupper((string) $transaction->currency),
        ]));
    }

    private function bookingStatusValue(Booking $booking): string
    {
        return $booking->status instanceof BookingStatus
            ? $booking->status->value
            : (string) $booking->status;
    }
}
