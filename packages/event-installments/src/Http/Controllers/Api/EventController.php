<?php

namespace Personal\EventInstallments\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Personal\EventInstallments\Models\Event;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Event::class);

        return Event::query()
            ->with(['schedules', 'ticketTypes', 'attendeeFields'])
            ->where('status', 'published')
            ->orderBy('name')
            ->paginate(50);
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return $event->load(['schedules', 'ticketTypes', 'attendeeFields', 'paymentPlans']);
    }
}
