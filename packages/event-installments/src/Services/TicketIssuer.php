<?php

namespace Personal\EventInstallments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Ticket;

class TicketIssuer
{
    /**
     * @return array<int, Ticket>
     */
    public function issueForBooking(Booking $booking): array
    {
        return DB::transaction(function () use ($booking) {
            $created = [];
            $booking->loadMissing('event', 'attendees.ticketType', 'tickets');

            foreach ($booking->attendees as $attendee) {
                if ($booking->tickets()->where('attendee_id', $attendee->id)->exists()) {
                    continue;
                }

                $number = $this->nextTicketNumber($booking);
                $fields = is_array($attendee->custom_fields) ? $attendee->custom_fields : [];
                $metadata = [];
                foreach (['family_name', 'family_role', 'family_age', 'family_parent_names', 'payment_exempt'] as $key) {
                    if (array_key_exists($key, $fields)) {
                        $metadata[$key] = $fields[$key];
                    }
                }
                if (array_key_exists('ticket_amount', $fields)) {
                    $metadata['amount_paid'] = (float) $fields['ticket_amount'];
                }

                $created[] = Ticket::query()->create([
                    'event_id' => $booking->event_id,
                    'booking_id' => $booking->id,
                    'attendee_id' => $attendee->id,
                    'ticket_type_id' => $attendee->ticket_type_id,
                    'ticket_number' => $number,
                    'formatted_number' => $this->formatTicketNumber($booking, $number),
                    'qr_hash' => hash('sha256', $booking->public_id . '|' . $attendee->public_id . '|' . Str::random(32)),
                    'status' => $this->initialStatus($booking),
                    'issued_at' => now(),
                    'metadata' => $metadata,
                ]);
            }

            return $created;
        });
    }

    public function formatTicketNumber(Booking $booking, string $number): string
    {
        $prefix = (string) config('event-installments.ticket.identifier_prefix', 'EVT');

        return $prefix . '-' . $booking->event_id . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    private function nextTicketNumber(Booking $booking): string
    {
        $last = Ticket::query()
            ->where('event_id', $booking->event_id)
            ->lockForUpdate()
            ->orderByRaw('CAST(ticket_number AS UNSIGNED) DESC')
            ->value('ticket_number');

        return (string) (((int) $last) + 1);
    }

    private function initialStatus(Booking $booking): TicketStatus
    {
        return match ($booking->status->value) {
            'paid' => TicketStatus::NotCheckedIn,
            'deposit_paid', 'partially_paid' => TicketStatus::Provisional,
            default => TicketStatus::Unpaid,
        };
    }
}
