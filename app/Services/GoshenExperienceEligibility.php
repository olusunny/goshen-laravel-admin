<?php

namespace App\Services;

use App\Models\MobileUser;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\Ticket;

class GoshenExperienceEligibility
{
    public function eligibleTicketFor(MobileUser $user, Event $event): ?Ticket
    {
        if (! $user->canUseCommunity()) {
            return null;
        }

        return Ticket::query()
            ->with(['booking', 'checkIns'])
            ->where('event_id', $event->id)
            ->whereHas('booking', function ($query) use ($user): void {
                $query
                    ->where('customer_id', $user->id)
                    ->whereIn('status', [
                        BookingStatus::DepositPaid->value,
                        BookingStatus::PartiallyPaid->value,
                        BookingStatus::Paid->value,
                    ]);
            })
            ->whereNotIn('status', [
                TicketStatus::Cancelled->value,
                TicketStatus::Unpaid->value,
            ])
            ->whereHas('checkIns', function ($query): void {
                $query->where('status', TicketStatus::CheckedIn->value);
            })
            ->latest('id')
            ->first();
    }

    public function eligibleBookingFor(MobileUser $user, Event $event): ?Booking
    {
        return $this->eligibleTicketFor($user, $event)?->booking;
    }

    public function checkedInMobileUsersFor(Event $event)
    {
        $checkedInUserIds = Ticket::query()
            ->join('ei_bookings', 'ei_bookings.id', '=', 'ei_tickets.booking_id')
            ->join('ei_ticket_check_ins', 'ei_ticket_check_ins.ticket_id', '=', 'ei_tickets.id')
            ->where('ei_tickets.event_id', $event->id)
            ->whereIn('ei_bookings.status', [
                BookingStatus::DepositPaid->value,
                BookingStatus::PartiallyPaid->value,
                BookingStatus::Paid->value,
            ])
            ->whereNotIn('ei_tickets.status', [
                TicketStatus::Cancelled->value,
                TicketStatus::Unpaid->value,
            ])
            ->where('ei_ticket_check_ins.status', TicketStatus::CheckedIn->value)
            ->whereNotNull('ei_bookings.customer_id')
            ->select('ei_bookings.customer_id')
            ->distinct();

        return MobileUser::query()
            ->where('is_verified', true)
            ->where('is_blocked', false)
            ->where('is_deleted', false)
            ->whereIn('id', $checkedInUserIds);
    }
}
