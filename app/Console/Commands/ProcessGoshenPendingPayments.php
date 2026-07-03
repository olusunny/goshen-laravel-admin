<?php

namespace App\Console\Commands;

use App\Services\GoshenBookingLifecycleService;
use Illuminate\Console\Command;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Booking;

class ProcessGoshenPendingPayments extends Command
{
    protected $signature = 'goshen:process-pending-payments
        {--limit=100 : Maximum pending bookings to inspect}';

    protected $description = 'Remind users and cancel unpaid Goshen Retreat bookings after their payment window expires.';

    public function handle(GoshenBookingLifecycleService $lifecycle): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $now = now();
        $checked = 0;
        $reminded = 0;
        $cancelled = 0;

        Booking::query()
            ->with('event')
            ->where('status', BookingStatus::Pending->value)
            ->where('paid_total', '<=', 0)
            ->whereHas('event', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('settings->module', 'goshen_retreat')
                        ->orWhere('settings->module', 'goshen-retreat')
                        ->orWhere('settings->app_module', 'goshen_retreat')
                        ->orWhere('slug', 'like', 'goshen-retreat%')
                        ->orWhere('slug', 'like', 'goshen-%')
                        ->orWhere('name', 'like', '%Goshen Retreat%');
                });
            })
            ->oldest()
            ->limit($limit)
            ->get()
            ->each(function (Booking $booking) use ($lifecycle, $now, &$checked, &$reminded, &$cancelled): void {
                $checked++;
                $expiresAt = $booking->payment_expires_at ?: $booking->created_at?->copy()->addDay();

                if (! $expiresAt) {
                    return;
                }

                if ($booking->payment_reminder_sent_at === null && $now->greaterThanOrEqualTo($expiresAt->copy()->subHour())) {
                    $lifecycle->sendPendingPaymentReminder($booking);
                    $reminded++;
                }

                if ($now->greaterThanOrEqualTo($expiresAt)) {
                    $lifecycle->cancelBooking(
                        $booking,
                        'Payment was not completed within 24 hours.',
                        notifyUser: true,
                    );
                    $cancelled++;
                }
            });

        $this->info("Checked {$checked} pending booking(s). {$reminded} reminder(s), {$cancelled} cancellation(s).");

        return self::SUCCESS;
    }
}
