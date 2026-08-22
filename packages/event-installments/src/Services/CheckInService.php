<?php

namespace Personal\EventInstallments\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketCheckIn;

class CheckInService
{
    public function findTicket(string $identifier): Ticket
    {
        $ticket = Ticket::query()
            ->where('public_id', $identifier)
            ->orWhere('ticket_number', $identifier)
            ->orWhere('formatted_number', $identifier)
            ->first();

        if (! $ticket) {
            throw (new ModelNotFoundException())->setModel(Ticket::class, [$identifier]);
        }

        return $ticket;
    }

    public function checkIn(
        Ticket $ticket,
        TicketStatus $status,
        ?int $actorId = null,
        int $dayNumber = 1,
        string $source = 'api',
        ?string $deviceId = null,
        array $metadata = [],
    ): TicketCheckIn {
        $checkIn = DB::transaction(function () use ($ticket, $status, $actorId, $dayNumber, $source, $deviceId, $metadata) {
            $ticket = Ticket::query()
                ->with('event')
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $submittedDayNumber = max(1, $dayNumber);
            if (! $ticket->event?->allowsCheckInDayNumber($submittedDayNumber)) {
                throw ValidationException::withMessages([
                    'day_number' => 'The selected check-in day is not configured for this event.',
                ]);
            }

            $effectiveDayNumber = $ticket->event?->effectiveCheckInDayNumber($submittedDayNumber) ?? $submittedDayNumber;
            $isEventDurationCheckIn = $ticket->event?->usesEventDurationCheckIn() ?? false;

            $existingQuery = TicketCheckIn::query()
                ->where('ticket_id', $ticket->id)
                ->lockForUpdate();

            if (! $isEventDurationCheckIn) {
                $existingQuery->where('day_number', $effectiveDayNumber);
            }

            $existing = $existingQuery->first();

            if ($existing) {
                return $existing;
            }

            $multidayStatus = $ticket->multiday_status ?: [];
            $multidayStatus[$effectiveDayNumber] = $status->value;

            $ticket->forceFill([
                'status' => ($isEventDurationCheckIn || $effectiveDayNumber === 1)
                    ? $status
                    : TicketStatus::NotCheckedIn,
                'multiday_status' => $multidayStatus,
            ])->save();

            return TicketCheckIn::query()->create([
                'ticket_id' => $ticket->id,
                'event_id' => $ticket->event_id,
                'actor_id' => $actorId,
                'day_number' => $effectiveDayNumber,
                'status' => $status,
                'checked_in_at' => now(),
                'source' => $source,
                'device_id' => $deviceId,
                'metadata' => array_replace([
                    'check_in_mode' => $ticket->event?->checkInMode() ?? 'per_day',
                    'submitted_day_number' => $submittedDayNumber,
                ], $metadata),
            ]);
        });

        if ($status === TicketStatus::CheckedIn && class_exists(\App\Services\GoshenReferralService::class)) {
            app(\App\Services\GoshenReferralService::class)->validateForTicketCheckIn($checkIn);
        }

        return $checkIn;
    }
}
