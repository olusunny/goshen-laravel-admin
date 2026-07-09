<?php

namespace Personal\EventInstallments\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Personal\EventInstallments\Http\Requests\Admin\StoreEventRequest;
use Personal\EventInstallments\Http\Requests\Admin\UpdateEventRequest;
use Personal\EventInstallments\Models\Event;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Event::class);

        return view('event-installments::admin.events.index', [
            'events' => Event::query()->latest()->paginate(25),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Event::class);

        return view('event-installments::admin.events.create', [
            'event' => new Event(['timezone' => config('app.timezone', 'UTC'), 'status' => 'draft']),
        ]);
    }

    public function store(StoreEventRequest $request)
    {
        $data = $request->validated();
        $data['owner_id'] = $request->user()?->getAuthIdentifier();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $event = Event::query()->create($data);

        return redirect()->route('event-installments.events.show', $event)->with('status', 'Event created.');
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return view('event-installments::admin.events.show', [
            'event' => $event->load(['schedules', 'ticketTypes', 'paymentPlans']),
        ]);
    }

    public function edit(Event $event)
    {
        $this->authorize('update', $event);

        return view('event-installments::admin.events.edit', ['event' => $event]);
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $event->update($data);

        return redirect()->route('event-installments.events.show', $event)->with('status', 'Event updated.');
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        $event->delete();

        return redirect()->route('event-installments.events.index')->with('status', 'Event archived.');
    }
}
