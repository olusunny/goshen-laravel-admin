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

                if ((int) $installment->sequence === 1) {
                    $this->rescheduleFutureInstallments($booking, $installment);
                }
            }

            $booking->refresh();

            $paidTotal = (float) PaymentInstallment::query()
                ->where('booking_id', $booking->id)
                ->sum('paid_amount');
            $paidInstallments = (int) $booking->installments()->where('status', InstallmentStatus::Paid->value)->count();
            $paymentPlan = $booking->paymentPlan;
            $bookingStatus = $paidTotal + 0.01 >= (float) $booking->total
                ? BookingStatus::Paid
                : ($paidInstallments === 1 ? BookingStatus::DepositPaid : BookingStatus::PartiallyPaid);

            $booking->forceFill([
                'paid_total' => $paidTotal,
                'status' => $bookingStatus,
                'auto_charge_failed_at' => null,
                'auto_charge_failure_reason' => null,
            ])->save();

            $shouldIssue = $bookingStatus === BookingStatus::Paid
                || ($paymentPlan?->ticket_issue_policy === 'deposit_paid' && $bookingStatus === BookingStatus::DepositPaid);

            if ($shouldIssue) {
                $createdTickets = $this->ticketIssuer->issueForBooking($booking);

                if (config('event-installments.ticket.email.enabled', true)) {
                    $ticketIds = collect($createdTickets)->pluck('id')->all();

                    DB::afterCommit(function () use ($ticketIds) {
                        Ticket::query()->whereKey($ticketIds)->get()->each(
                            fn (Ticket $ticket) => $this->notifications->sendTicket($ticket),
                        );
                    });
                }
            }

            if ($bookingStatus === BookingStatus::Paid && class_exists(\App\Services\GoshenReferralService::class)) {
                app(\App\Services\GoshenReferralService::class)->createPendingAwardForPaidBooking(
                    $booking->fresh(['event', 'attendees']),
                );
            }
        });
    }

    private function rescheduleFutureInstallments(Booking $booking, PaymentInstallment $paidInstallment): void
    {
        $paymentPlan = $booking->paymentPlan;

        if (! $paymentPlan || (int) $paymentPlan->interval_days < 1) {
            return;
        }

        $anchor = $paidInstallment->paid_at ?: now();
        $intervalDays = (int) $paymentPlan->interval_days;

        $booking->installments()
            ->where('sequence', '>', (int) $paidInstallment->sequence)
            ->whereIn('status', [InstallmentStatus::Pending->value, InstallmentStatus::Failed->value])
            ->orderBy('sequence')
            ->get()
            ->each(function (PaymentInstallment $futureInstallment) use ($anchor, $intervalDays): void {
                $futureInstallment->forceFill([
                    'due_on' => $anchor->copy()->addDays(((int) $futureInstallment->sequence - 1) * $intervalDays)->toDateString(),
                    'status' => InstallmentStatus::Pending,
                ])->save();
            });
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
