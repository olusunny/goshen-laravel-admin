<?php

namespace Personal\EventInstallments\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Personal\EventInstallments\Http\Requests\Admin\StoreTicketTypeRequest;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;

class TicketTypeController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreTicketTypeRequest $request, Event $event)
    {
        $data = $request->validated();
        $data['currency'] = strtoupper($data['currency']);
        $data['is_active'] = $request->boolean('is_active', true);

        $event->ticketTypes()->create($data);

        return back()->with('status', 'Ticket type added.');
    }

    public function destroy(Event $event, EventTicketType $ticketType)
    {
        $this->authorize('update', $event);
        abort_unless($ticketType->event_id === $event->id, 404);
        $ticketType->delete();

        return back()->with('status', 'Ticket type removed.');
    }
}
