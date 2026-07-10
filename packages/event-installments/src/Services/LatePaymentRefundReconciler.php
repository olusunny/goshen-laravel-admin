<?php

namespace Personal\EventInstallments\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Models\PaymentGatewayWebhookEvent;
use Personal\EventInstallments\Models\PaymentTransaction;
use RuntimeException;
use Throwable;

class LatePaymentRefundReconciler
{
    private const LEASE_SECONDS = 300;

    /**
     * @return 'leased'|'manual_reconciliation_required'|'pending'|'refunded'|'skipped'
     */
    public function reconcile(int $transactionId, PaymentGateway $gateway, ?int $eventId = null): string
    {
        $claim = $this->claim($transactionId, $eventId);
        if ($claim === null) {
            $status = (string) PaymentTransaction::query()->whereKey($transactionId)->value('status');

            return match ($status) {
                'refund_pending' => 'leased',
                'refunded' => 'refunded',
                'manual_reconciliation_required' => 'manual_reconciliation_required',
                default => 'skipped',
            };
        }

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);

        try {
            $refund = $gateway->refund($transaction, $claim['amount'], $claim['refund_key']);
        } catch (Throwable $exception) {
            $this->recordDispatchError($transactionId, $claim['lease_token'], $exception);
            throw $exception;
        }

        return $this->persistProviderResult(
            $transactionId,
            $claim['lease_token'],
            $claim['event_id'],
            $refund,
        );
    }

    /** @return array{amount: float, refund_key: string, lease_token: string, event_id: int|null}|null */
    private function claim(int $transactionId, ?int $eventId): ?array
    {
        return DB::transaction(function () use ($transactionId, $eventId): ?array {
            $transaction = PaymentTransaction::query()->whereKey($transactionId)->lockForUpdate()->firstOrFail();
            if ((string) $transaction->status !== 'refund_pending') {
                return null;
            }

            $payload = $transaction->payload ?: [];
            $reconciliation = is_array($payload['late_terminal_reconciliation'] ?? null)
                ? $payload['late_terminal_reconciliation']
                : [];
            $refundKey = trim((string) ($reconciliation['refund_key'] ?? ''));
            $amount = (float) ($reconciliation['refunded_amount'] ?? 0);
            if ($refundKey === '' || $amount <= 0) {
                throw new RuntimeException('Refund intent is missing its stable key or amount.');
            }

            $lease = is_array($reconciliation['dispatch_lease'] ?? null)
                ? $reconciliation['dispatch_lease']
                : [];
            $expiresAt = $this->parseTimestamp($lease['expires_at'] ?? null);
            if (filled($lease['token'] ?? null) && $expiresAt?->isFuture()) {
                return null;
            }

            $token = (string) Str::uuid();
            $claimedAt = now();
            $reconciliation['dispatch_lease'] = [
                'token' => $token,
                'claimed_at' => $claimedAt->toIso8601String(),
                'expires_at' => $claimedAt->copy()->addSeconds(self::LEASE_SECONDS)->toIso8601String(),
            ];
            $reconciliation['dispatch_attempts'] = ((int) ($reconciliation['dispatch_attempts'] ?? 0)) + 1;
            $reconciliation['last_dispatch_started_at'] = $claimedAt->toIso8601String();
            if ($eventId !== null) {
                $reconciliation['webhook_event_id'] = $eventId;
            }

            $transaction->forceFill([
                'payload' => array_merge($payload, ['late_terminal_reconciliation' => $reconciliation]),
            ])->save();

            return [
                'amount' => $amount,
                'refund_key' => $refundKey,
                'lease_token' => $token,
                'event_id' => $eventId ?? (isset($reconciliation['webhook_event_id']) ? (int) $reconciliation['webhook_event_id'] : null),
            ];
        });
    }

    /**
     * @return 'manual_reconciliation_required'|'pending'|'refunded'|'skipped'
     */
    protected function persistProviderResult(
        int $transactionId,
        string $leaseToken,
        ?int $eventId,
        RefundResult $refund,
    ): string {
        return DB::transaction(function () use ($transactionId, $leaseToken, $eventId, $refund): string {
            $transaction = PaymentTransaction::query()->whereKey($transactionId)->lockForUpdate()->firstOrFail();
            if ((string) $transaction->status !== 'refund_pending') {
                return (string) $transaction->status;
            }

            $payload = $transaction->payload ?: [];
            $reconciliation = is_array($payload['late_terminal_reconciliation'] ?? null)
                ? $payload['late_terminal_reconciliation']
                : [];
            if (($reconciliation['dispatch_lease']['token'] ?? null) !== $leaseToken) {
                return 'skipped';
            }

            $providerStatus = strtolower(trim($refund->status));
            $reconciliation = array_merge($reconciliation, [
                'refund_reference' => $refund->reference,
                'refund_status' => $providerStatus,
                'refund_payload' => $refund->payload,
                'provider_last_checked_at' => now()->toIso8601String(),
            ]);

            if ($this->isTerminalSuccess($providerStatus)) {
                $reconciliation['action'] = 'automatic_refund';
                $reconciliation['refunded_at'] = now()->toIso8601String();
                unset($reconciliation['dispatch_lease']);
                $transaction->forceFill([
                    'status' => 'refunded',
                    'payload' => array_merge($payload, ['late_terminal_reconciliation' => $reconciliation]),
                ])->save();
                $this->completeWebhookEvents($transaction, $reconciliation, $eventId);

                return 'refunded';
            }

            if ($this->isPending($providerStatus)) {
                $reconciliation['action'] = 'refund_pending';
                $transaction->forceFill([
                    'status' => 'refund_pending',
                    'payload' => array_merge($payload, ['late_terminal_reconciliation' => $reconciliation]),
                ])->save();

                return 'pending';
            }

            $reconciliation['action'] = 'manual_reconciliation_required';
            $reconciliation['manual_reconciliation_reason'] = 'provider_refund_status_'.($providerStatus ?: 'unknown');
            unset($reconciliation['dispatch_lease']);
            $transaction->forceFill([
                'status' => 'manual_reconciliation_required',
                'payload' => array_merge($payload, ['late_terminal_reconciliation' => $reconciliation]),
            ])->save();
            $this->completeWebhookEvents($transaction, $reconciliation, $eventId);

            return 'manual_reconciliation_required';
        });
    }

    private function recordDispatchError(int $transactionId, string $leaseToken, Throwable $exception): void
    {
        DB::transaction(function () use ($transactionId, $leaseToken, $exception): void {
            $transaction = PaymentTransaction::query()->whereKey($transactionId)->lockForUpdate()->first();
            if (! $transaction || (string) $transaction->status !== 'refund_pending') {
                return;
            }

            $payload = $transaction->payload ?: [];
            $reconciliation = is_array($payload['late_terminal_reconciliation'] ?? null)
                ? $payload['late_terminal_reconciliation']
                : [];
            if (($reconciliation['dispatch_lease']['token'] ?? null) !== $leaseToken) {
                return;
            }

            $reconciliation['last_dispatch_error'] = $exception->getMessage();
            $reconciliation['last_dispatch_error_at'] = now()->toIso8601String();
            $transaction->forceFill([
                'payload' => array_merge($payload, ['late_terminal_reconciliation' => $reconciliation]),
            ])->save();
        });
    }

    private function completeWebhookEvents(PaymentTransaction $transaction, array $reconciliation, ?int $eventId): void
    {
        $eventIds = collect($reconciliation['provider_event_ids'] ?? [])->filter()->values();
        if ($eventIds->isEmpty() && $eventId === null) {
            return;
        }

        PaymentGatewayWebhookEvent::query()
            ->where('gateway', $transaction->gateway)
            ->where(function ($query) use ($eventIds, $eventId): void {
                if ($eventIds->isNotEmpty()) {
                    $query->whereIn('provider_event_id', $eventIds);
                }
                if ($eventId !== null) {
                    $eventIds->isNotEmpty()
                        ? $query->orWhere('id', $eventId)
                        : $query->where('id', $eventId);
                }
            })
            ->update(['status' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);
    }

    private function isTerminalSuccess(string $status): bool
    {
        return in_array($status, ['succeeded', 'success', 'successful', 'processed', 'refunded', 'completed', 'complete'], true);
    }

    private function isPending(string $status): bool
    {
        return in_array($status, ['pending', 'processing', 'queued'], true);
    }

    private function parseTimestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
