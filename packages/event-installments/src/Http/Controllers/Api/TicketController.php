<?php

namespace Personal\EventInstallments\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\CheckInService;
use Personal\EventInstallments\Services\QrPayloadService;

class TicketController extends Controller
{
    use AuthorizesRequests;

    public function index(Event $event)
    {
        $this->authorize('view', $event);

        return $event->tickets()
            ->with(['attendee', 'ticketType'])
            ->latest('updated_at')
            ->paginate((int) request('per_page', 100));
    }

    public function updated(Event $event, Request $request)
    {
        $this->authorize('view', $event);

        $since = $request->date('since') ?: now()->subDay();

        return $event->tickets()
            ->with(['attendee', 'ticketType'])
            ->where('updated_at', '>=', $since)
            ->latest('updated_at')
            ->paginate((int) $request->input('per_page', 100));
    }

    public function show(string $identifier, CheckInService $tickets, QrPayloadService $qrPayload)
    {
        $ticket = $tickets->findTicket($identifier)->load(['event', 'booking', 'attendee', 'ticketType']);
        $this->authorize('view', $ticket);

        return [
            'ticket' => $ticket,
            'qr_payload' => $qrPayload->payloadFor($ticket),
            'qr_encoded' => $qrPayload->encodedPayloadFor($ticket),
        ];
    }
}
