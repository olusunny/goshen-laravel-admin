<?php

namespace App\Services;

use App\Models\MobileUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\EventTicketType;

class GoshenRegistrationAvailabilityService
{
    /**
     * Lock order is always member then ticket type.
     *
     * @return array{MobileUser, EventTicketType}
     */
    public function lockAndAssertAvailable(
        MobileUser $member,
        EventTicketType $ticketType,
        int $quantity = 1,
    ): array {
        return DB::transaction(function () use ($member, $ticketType, $quantity): array {
            $member = MobileUser::query()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $ticketType = EventTicketType::query()
                ->with('event')
                ->whereKey($ticketType->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $member->canUseCommunity()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Registrations can only be created for active verified app members.',
                ]);
            }

            if (! $ticketType->is_active
                || ! $ticketType->event
                || $ticketType->event->status !== 'published') {
                throw ValidationException::withMessages([
                    'ticket_type_id' => 'Select an active ticket type for an available retreat edition.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Register at least one attendee.',
                ]);
            }

            $activeLines = BookingLine::query()
                ->where('ticket_type_id', $ticketType->id)
                ->whereHas('booking', fn ($query) => $query
                    ->where('event_id', $ticketType->event_id)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', [
                        BookingStatus::Cancelled->value,
                        BookingStatus::Refunded->value,
                    ]));

            $memberAlreadyReserved = (clone $activeLines)
                ->whereHas('booking', fn ($query) => $query
                    ->where('customer_id', $member->id))
                ->exists();

            if ($memberAlreadyReserved) {
                throw ValidationException::withMessages([
                    'customer_id' => 'This member already has a registration for this ticket type and retreat edition.',
                ]);
            }

            $capacity = (int) ($ticketType->capacity ?? 0);
            if ($capacity > 0) {
                $reservedQuantity = (int) (clone $activeLines)->sum('quantity');
                if ($reservedQuantity + $quantity > $capacity) {
                    throw ValidationException::withMessages([
                        'ticket_type_id' => 'This ticket type is sold out for the selected retreat edition.',
                    ]);
                }
            }

            return [$member, $ticketType];
        });
    }
}
