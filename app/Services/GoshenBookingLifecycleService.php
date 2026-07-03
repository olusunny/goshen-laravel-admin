<?php

namespace App\Services;

use App\Models\MobileUser;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;

class GoshenBookingLifecycleService
{
    public function __construct(
        private readonly GoshenRetreatNotificationService $notifications,
    ) {
    }

    public function cancelBooking(
        Booking $booking,
        string $reason,
        ?int $cancelledById = null,
        bool $notifyUser = true,
    ): Booking {
        $booking = DB::transaction(function () use ($booking, $reason, $cancelledById): Booking {
            /** @var Booking $locked */
            $locked = Booking::query()
                ->with(['installments', 'tickets', 'event'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            $status = $locked->status instanceof BookingStatus ? $locked->status : BookingStatus::tryFrom((string) $locked->status);

            if (in_array($status, [BookingStatus::Paid, BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
                return $locked;
            }

            $locked->installments()
                ->whereIn('status', [
                    InstallmentStatus::Pending->value,
                    InstallmentStatus::Processing->value,
                    InstallmentStatus::Failed->value,
                    InstallmentStatus::Overdue->value,
                ])
                ->update([
                    'status' => InstallmentStatus::Cancelled->value,
                    'updated_at' => now(),
                ]);

            $locked->tickets()
                ->where('status', '!=', TicketStatus::CheckedIn->value)
                ->update([
                    'status' => TicketStatus::Cancelled->value,
                    'updated_at' => now(),
                ]);

            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $metadata['cancelled_reason'] = $reason;

            $locked->forceFill([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_id' => $cancelledById,
                'cancellation_reason' => $reason,
                'auto_charge_enabled' => false,
                'metadata' => $metadata,
            ])->save();

            return $locked->refresh()->load(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets']);
        });

        if ($notifyUser && $booking->customer_id) {
            $user = MobileUser::query()->find($booking->customer_id);

            if ($user) {
                $this->sendCancellationNotice($booking, $user, $reason);
            }
        }

        return $booking;
    }

    public function sendPendingPaymentReminder(Booking $booking): void
    {
        if (! $booking->customer_id || $booking->payment_reminder_sent_at) {
            return;
        }

        $user = MobileUser::query()->find($booking->customer_id);

        if (! $user) {
            return;
        }

        $expiry = $booking->payment_expires_at ?: $booking->created_at?->copy()->addDay();
        $eventName = $booking->event?->name ?: 'Goshen Retreat';
        $amount = trim(($booking->currency ?: '') . ' ' . number_format((float) $booking->total, 2));

        $body = "Hello {$user->name},\n\n"
            . "This is a friendly reminder that your {$eventName} registration payment is still pending.\n\n"
            . "Booking reference: {$booking->public_id}\n"
            . "Amount: {$amount}\n"
            . 'Payment window closes: ' . ($expiry?->format('M j, Y g:i A') ?: 'soon') . "\n\n"
            . "Kindly complete the payment from your profile if you still want to keep this registration. If you no longer need it, you may cancel it from the app.\n\n"
            . "Thank you, and God bless you.";

        $this->notifications->notifyUser($user, 'Goshen Retreat payment reminder', $body, 'events');

        $booking->forceFill(['payment_reminder_sent_at' => now()])->save();
    }

    private function sendCancellationNotice(Booking $booking, MobileUser $user, string $reason): void
    {
        $eventName = $booking->event?->name ?: 'Goshen Retreat';
        $body = "Hello {$user->name},\n\n"
            . "Your pending {$eventName} registration has been cancelled.\n\n"
            . "Booking reference: {$booking->public_id}\n"
            . "Reason: {$reason}\n\n"
            . "If you still want to attend, you can start a fresh registration in the app. We will be happy to have you with us.";

        $this->notifications->notifyUser($user, 'Goshen Retreat registration cancelled', $body, 'events');
    }
}
