<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\EventAuditLog;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\TicketIssuer;

class GoshenAdminTicketIssuanceService
{
    public function __construct(private readonly TicketIssuer $ticketIssuer) {}

    public function issue(
        MobileUser $member,
        EventTicketType $ticketType,
        User $admin,
        string $reason,
    ): Ticket {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'issuance_reason' => 'Enter a reason for issuing this ticket.',
            ]);
        }

        return DB::transaction(function () use ($member, $ticketType, $admin, $reason): Ticket {
            $member = MobileUser::query()->lockForUpdate()->findOrFail($member->getKey());
            $ticketType = EventTicketType::query()
                ->with('event')
                ->lockForUpdate()
                ->findOrFail($ticketType->getKey());

            if ($member->is_blocked || $member->is_deleted) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Tickets can only be issued to active app members.',
                ]);
            }

            if (! $ticketType->is_active || ! $ticketType->event || $ticketType->event->status !== 'published') {
                throw ValidationException::withMessages([
                    'ticket_type_id' => 'Select an active ticket type for an available retreat edition.',
                ]);
            }

            $duplicateExists = Ticket::query()
                ->where('event_id', $ticketType->event_id)
                ->where('ticket_type_id', $ticketType->id)
                ->where('status', '!=', TicketStatus::Cancelled->value)
                ->whereHas('booking', fn ($query) => $query->where('customer_id', $member->id))
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'customer_id' => 'This member already has this ticket type for the selected retreat edition.',
                ]);
            }

            $listPrice = round((float) $ticketType->price, 2);
            $metadata = [
                'source' => 'filament_admin',
                'complimentary' => true,
                'listed_ticket_price' => $listPrice,
                'issued_by_admin_id' => $admin->id,
                'issuance_reason' => $reason,
            ];

            $booking = Booking::query()->create([
                'event_id' => $ticketType->event_id,
                'customer_id' => $member->id,
                'customer_name' => $member->name,
                'customer_email' => $member->email,
                'customer_phone' => $member->phone,
                'currency' => strtoupper((string) $ticketType->currency),
                'subtotal' => $listPrice,
                'total' => 0,
                'paid_total' => 0,
                'status' => BookingStatus::Paid,
                'metadata' => $metadata,
            ]);

            BookingLine::query()->create([
                'booking_id' => $booking->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => 1,
                'currency' => strtoupper((string) $ticketType->currency),
                'unit_price' => $listPrice,
                'line_total' => $listPrice,
                'metadata' => [
                    'complimentary' => true,
                    'waived_amount' => $listPrice,
                ],
            ]);

            Attendee::query()->create([
                'booking_id' => $booking->id,
                'ticket_type_id' => $ticketType->id,
                'first_name' => $member->first_name ?: $member->name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'custom_fields' => array_filter([
                    'title' => $member->title,
                    'gender' => $member->gender,
                    'marital_status' => $member->marital_status,
                ], fn ($value): bool => filled($value)),
            ]);

            $ticket = collect($this->ticketIssuer->issueForBooking($booking->fresh()))->first();

            if (! $ticket instanceof Ticket) {
                throw ValidationException::withMessages([
                    'ticket_type_id' => 'The ticket could not be issued. Please try again.',
                ]);
            }

            $ticket->forceFill(['metadata' => $metadata])->save();

            EventAuditLog::query()->create([
                'event_id' => $ticketType->event_id,
                'actor_id' => $admin->id,
                'action' => 'admin_ticket_issued',
                'auditable_type' => $ticket::class,
                'auditable_id' => $ticket->id,
                'after' => [
                    'ticket_public_id' => $ticket->public_id,
                    'member_id' => $member->id,
                    'ticket_type_id' => $ticketType->id,
                ],
                'metadata' => $metadata,
            ]);

            return $ticket->fresh(['booking', 'attendee', 'ticketType', 'event']);
        });
    }
}
