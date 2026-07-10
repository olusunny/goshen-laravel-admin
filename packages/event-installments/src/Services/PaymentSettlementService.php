<?php

namespace Personal\EventInstallments\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;

class PaymentSettlementService
{
    public function __construct(
        private readonly TicketIssuer $ticketIssuer,
        private readonly TicketNotificationService $notifications,
    ) {
    }

    public function markPaid(PaymentTransaction $transaction, ?float $paidAmount = null, ?string $currency = null): void
    {
        DB::transaction(function () use ($transaction, $paidAmount, $currency) {
            $transaction->refresh();

            $installment = $transaction->installment()->lockForUpdate()->first();
            $booking = $transaction->booking()->lockForUpdate()->firstOrFail();

            $bookingStatus = $booking->status instanceof BookingStatus
                ? $booking->status
                : BookingStatus::tryFrom((string) $booking->status);

            if (in_array($bookingStatus, [BookingStatus::Paid, BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
                $transaction->forceFill([
                    'status' => $bookingStatus === BookingStatus::Paid ? 'duplicate_paid' : 'paid_after_' . $bookingStatus->value,
                    'payload' => array_filter(array_merge(
                        $transaction->payload ?: [],
                        ['settlement_blocked_reason' => 'Booking is already ' . $bookingStatus->value],
                    )),
                ])->save();

                return;
            }

            $installmentStatus = $installment?->status instanceof InstallmentStatus
                ? $installment->status
                : ($installment ? InstallmentStatus::tryFrom((string) $installment->status) : null);

            if (in_array($installmentStatus, [InstallmentStatus::Paid, InstallmentStatus::Cancelled, InstallmentStatus::Refunded], true)) {
                $transaction->forceFill([
                    'status' => $installmentStatus === InstallmentStatus::Paid ? 'duplicate_paid' : 'paid_after_' . $installmentStatus->value,
                    'payload' => array_filter(array_merge(
                        $transaction->payload ?: [],
                        ['settlement_blocked_reason' => 'Installment is already ' . $installmentStatus->value],
                    )),
                ])->save();

                return;
            }

            $this->assertSingleFullPayment($booking, $installment, $transaction, $paidAmount, $currency);

            if ($currency !== null && strtoupper($currency) !== strtoupper((string) $transaction->currency)) {
                throw new InvalidArgumentException('Payment currency does not match this installment.');
            }

            $effectiveAmount = $paidAmount !== null && $paidAmount > 0
                ? $paidAmount
                : (float) $transaction->amount;
            $paidAt = now();

            if ($effectiveAmount + 0.01 < (float) $transaction->amount) {
                throw new InvalidArgumentException('Payment amount is lower than the expected installment amount.');
            }

            $transaction->forceFill([
                'status' => 'paid',
                'paid_at' => $paidAt,
            ])->save();

            if ($installment && $installment->status !== InstallmentStatus::Paid) {
                $installment->forceFill([
                    'paid_amount' => $effectiveAmount,
                    'paid_at' => $paidAt,
                    'status' => InstallmentStatus::Paid,
                ])->save();

            }

            $booking->refresh();

            $booking->forceFill([
                'paid_total' => (float) $booking->total,
                'status' => BookingStatus::Paid,
                'auto_charge_failed_at' => null,
                'auto_charge_failure_reason' => null,
            ])->save();

            $createdTickets = $this->ticketIssuer->issueForBooking($booking);

            if (config('event-installments.ticket.email.enabled', true)) {
                $ticketIds = collect($createdTickets)->pluck('id')->all();

                DB::afterCommit(function () use ($ticketIds) {
                    Ticket::query()->whereKey($ticketIds)->get()->each(
                        fn (Ticket $ticket) => $this->notifications->sendTicket($ticket),
                    );
                });
            }

            if (class_exists(\App\Services\GoshenReferralService::class)) {
                app(\App\Services\GoshenReferralService::class)->createPendingAwardForPaidBooking(
                    $booking->fresh(['event', 'attendees']),
                );
            }
        });
    }

    private function assertSingleFullPayment(
        Booking $booking,
        ?PaymentInstallment $record,
        PaymentTransaction $transaction,
        ?float $paidAmount,
        ?string $currency,
    ): void
    {
        if (! $record || $booking->payment_plan_id !== null) {
            throw new InvalidArgumentException('This registration must use one complete payment without a payment plan.');
        }

        $recordCount = (int) PaymentInstallment::query()->where('booking_id', $booking->id)->count();
        $effectiveAmount = $paidAmount !== null && $paidAmount > 0 ? $paidAmount : (float) $transaction->amount;
        $expectedCurrency = strtoupper((string) $booking->currency);
        $suppliedCurrency = strtoupper((string) ($currency ?? $transaction->currency));
        $bookingStatus = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);

        if ($recordCount !== 1
            || (int) $record->sequence !== 1
            || (int) $transaction->booking_id !== (int) $booking->id
            || (int) $transaction->installment_id !== (int) $record->id
            || (float) $booking->total <= 0
            || (float) $booking->paid_total > 0.0001
            || (float) $record->paid_amount > 0.0001
            || abs((float) $record->amount - (float) $booking->total) > 0.009
            || abs((float) $transaction->amount - (float) $booking->total) > 0.009
            || abs($effectiveAmount - (float) $booking->total) > 0.009
            || strtoupper((string) $record->currency) !== $expectedCurrency
            || strtoupper((string) $transaction->currency) !== $expectedCurrency
            || $suppliedCurrency !== $expectedCurrency
            || in_array($bookingStatus, [BookingStatus::DepositPaid, BookingStatus::PartiallyPaid], true)
            || (bool) data_get($booking->metadata, 'legacy_payment_review_required', false)) {
            throw new InvalidArgumentException('Only one complete payment for the full registration total can be settled.');
        }
    }

    public function markFailed(PaymentTransaction $transaction, string $reason): void
    {
        DB::transaction(function () use ($transaction, $reason) {
            $transaction->forceFill(['status' => 'failed'])->save();

            $transaction->installment?->forceFill([
                'status' => InstallmentStatus::Failed,
                'metadata' => array_filter(array_merge(
                    $transaction->installment->metadata ?: [],
                    ['last_failure_reason' => $reason],
                )),
            ])->save();

            $transaction->booking?->forceFill([
                'auto_charge_failed_at' => now(),
                'auto_charge_failure_reason' => $reason,
            ])->save();
        });
    }
}
