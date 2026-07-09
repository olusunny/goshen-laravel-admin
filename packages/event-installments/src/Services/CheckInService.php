<?php

namespace Personal\EventInstallments\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = TicketCheckIn::query()
                ->where('ticket_id', $ticket->id)
                ->where('day_number', $dayNumber)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $multidayStatus = $ticket->multiday_status ?: [];
            $multidayStatus[$dayNumber] = $status->value;

            $ticket->forceFill([
                'status' => $dayNumber === 1 ? $status : TicketStatus::NotCheckedIn,
                'multiday_status' => $multidayStatus,
            ])->save();

            return TicketCheckIn::query()->create([
                'ticket_id' => $ticket->id,
                'event_id' => $ticket->event_id,
                'actor_id' => $actorId,
                'day_number' => $dayNumber,
                'status' => $status,
                'checked_in_at' => now(),
                'source' => $source,
                'device_id' => $deviceId,
                'metadata' => $metadata,
            ]);
        });

        if ($status === TicketStatus::CheckedIn && class_exists(\App\Services\GoshenReferralService::class)) {
            app(\App\Services\GoshenReferralService::class)->validateForTicketCheckIn($checkIn);
        }

        return $checkIn;
    }
}
