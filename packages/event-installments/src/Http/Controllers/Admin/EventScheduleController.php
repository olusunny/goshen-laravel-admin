<?php

namespace Personal\EventInstallments\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Personal\EventInstallments\Http\Requests\Admin\StoreScheduleRequest;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;

class EventScheduleController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreScheduleRequest $request, Event $event)
    {
        $event->schedules()->updateOrCreate(
            ['day_number' => $request->integer('day_number')],
            $request->validated(),
        );

        return back()->with('status', 'Schedule saved.');
    }

    public function destroy(Event $event, EventSchedule $schedule)
    {
        $this->authorize('update', $event);
        abort_unless($schedule->event_id === $event->id, 404);
        $schedule->delete();

        return back()->with('status', 'Schedule removed.');
    }
}
