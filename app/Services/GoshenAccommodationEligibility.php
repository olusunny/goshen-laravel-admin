<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Ticket;

class GoshenAccommodationEligibility
{
    public function isEligibleAttendee(Attendee $attendee, ?int $eventId = null): bool
    {
        $attendee->loadMissing(['booking', 'ticket']);

        if (! $attendee->booking) {
            return false;
        }

        if ($eventId && (int) $attendee->booking->event_id !== (int) $eventId) {
            return false;
        }

        if (! $this->bookingHasAcceptedPayment($attendee->booking->status?->value ?? $attendee->booking->status)) {
            return false;
        }

        return $attendee->ticket instanceof Ticket
            && $this->isUsableTicket($attendee->ticket, $eventId);
    }

    public function isUsableTicket(Ticket $ticket, ?int $eventId = null, ?int $attendeeId = null): bool
    {
        if ($eventId && (int) $ticket->event_id !== (int) $eventId) {
            return false;
        }

        if ($attendeeId && (int) $ticket->attendee_id !== (int) $attendeeId) {
            return false;
        }

        $status = $ticket->status?->value ?? $ticket->status;

        return in_array($status, [
            TicketStatus::NotCheckedIn->value,
            TicketStatus::CheckedIn->value,
            TicketStatus::Provisional->value,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateAndHydrateAllocationData(array $data): array
    {
        $eventId = isset($data['event_id']) ? (int) $data['event_id'] : null;
        $attendeeId = isset($data['attendee_id']) ? (int) $data['attendee_id'] : null;

        $attendee = $attendeeId ? Attendee::query()->with(['booking', 'ticket'])->find($attendeeId) : null;

        if (! $attendee || ! $this->isEligibleAttendee($attendee, $eventId)) {
            throw ValidationException::withMessages([
                'attendee_id' => 'Accommodation can only be assigned to attendees with an accepted Goshen Retreat payment and an active ticket.',
            ]);
        }

        $ticket = null;
        if (! empty($data['ticket_id'])) {
            $ticket = Ticket::query()->find((int) $data['ticket_id']);

            if (! $ticket || ! $this->isUsableTicket($ticket, $eventId, $attendee->id)) {
                throw ValidationException::withMessages([
                    'ticket_id' => 'Please select an active ticket that belongs to the selected paid attendee.',
                ]);
            }
        }

        $data['ticket_id'] = $ticket?->id ?: $attendee->ticket?->id;
        $data['assigned_at'] = $data['assigned_at'] ?? now();

        return $data;
    }

    private function bookingHasAcceptedPayment(?string $status): bool
    {
        return in_array($status, [
            BookingStatus::DepositPaid->value,
            BookingStatus::PartiallyPaid->value,
            BookingStatus::Paid->value,
        ], true);
    }
}
