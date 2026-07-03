<?php

namespace Personal\EventInstallments\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Http\Requests\BulkCheckInRequest;
use Personal\EventInstallments\Http\Requests\CheckInTicketRequest;
use Personal\EventInstallments\Services\CheckInService;

class TicketCheckInController extends Controller
{
    use AuthorizesRequests;

    public function store(string $identifier, CheckInTicketRequest $request, CheckInService $checkIns)
    {
        $ticket = $checkIns->findTicket($identifier);
        $this->authorize('checkIn', $ticket);

        return $checkIns->checkIn(
            ticket: $ticket,
            status: TicketStatus::from((string) $request->input('status')),
            actorId: $request->user()?->getAuthIdentifier(),
            source: $request->input('source', 'api'),
            deviceId: $request->input('device_id'),
            metadata: $request->input('metadata', []),
        );
    }

    public function storeForDay(string $identifier, int $day, CheckInTicketRequest $request, CheckInService $checkIns)
    {
        $ticket = $checkIns->findTicket($identifier);
        $this->authorize('checkIn', $ticket);

        return $checkIns->checkIn(
            ticket: $ticket,
            status: TicketStatus::from((string) $request->input('status')),
            actorId: $request->user()?->getAuthIdentifier(),
            dayNumber: $day,
            source: $request->input('source', 'api'),
            deviceId: $request->input('device_id'),
            metadata: $request->input('metadata', []),
        );
    }

    public function bulkStore(BulkCheckInRequest $request, CheckInService $checkIns)
    {
        $results = [];

        foreach ($request->input('tickets') as $item) {
            $ticket = $checkIns->findTicket($item['identifier']);
            $this->authorize('checkIn', $ticket);
            $results[] = $checkIns->checkIn(
                ticket: $ticket,
                status: TicketStatus::from($item['status']),
                actorId: $request->user()?->getAuthIdentifier(),
                dayNumber: (int) ($item['day_number'] ?? 1),
                source: $request->input('source', 'api'),
                deviceId: $request->input('device_id'),
                metadata: $request->input('metadata', []),
            );
        }

        return ['data' => $results];
    }
}
