@extends('event-installments::admin.layout')

@section('title', $event->name)

@section('actions')
    <a class="button secondary" href="{{ route('event-installments.events.edit', $event) }}">Edit Event</a>
@endsection

@section('content')
    <div class="panel">
        <div class="grid-3">
            <div><strong>Status</strong><br>{{ $event->status }}</div>
            <div><strong>Type</strong><br>{{ $event->type?->value ?? $event->type }}</div>
            <div><strong>Timezone</strong><br>{{ $event->timezone }}</div>
        </div>
        <p class="muted">{{ $event->description }}</p>
    </div>

    <div class="panel">
        <h2>Schedules</h2>
        <table>
            <thead><tr><th>Day</th><th>Starts</th><th>Ends</th><th>Capacity</th><th></th></tr></thead>
            <tbody>
                @foreach($event->schedules as $schedule)
                    <tr>
                        <td>{{ $schedule->day_number }}</td>
                        <td>{{ $schedule->starts_at->format('Y-m-d H:i') }}</td>
                        <td>{{ optional($schedule->ends_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $schedule->capacity ?: 'Unlimited' }}</td>
                        <td>
                            <form method="post" action="{{ route('event-installments.events.schedules.destroy', [$event, $schedule]) }}">
                                @csrf @method('DELETE')
                                <button class="danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <form method="post" action="{{ route('event-installments.events.schedules.store', $event) }}" class="grid-3" style="margin-top:14px">
            @csrf
            <div><label>Day</label><input name="day_number" type="number" min="1" value="1"></div>
            <div><label>Starts</label><input name="starts_at" type="datetime-local" required></div>
            <div><label>Ends</label><input name="ends_at" type="datetime-local"></div>
            <div><label>Capacity</label><input name="capacity" type="number" min="1"></div>
            <div class="actions" style="align-self:end"><button type="submit">Save Schedule</button></div>
        </form>
    </div>

    <div class="panel">
        <h2>Ticket Types</h2>
        <table>
            <thead><tr><th>Name</th><th>Price</th><th>Capacity</th><th>Per Booking</th><th></th></tr></thead>
            <tbody>
                @foreach($event->ticketTypes as $ticketType)
                    <tr>
                        <td>{{ $ticketType->name }}<div class="muted">{{ $ticketType->public_id }}</div></td>
                        <td>{{ $ticketType->currency }} {{ $ticketType->price }}</td>
                        <td>{{ $ticketType->capacity ?: 'Unlimited' }}</td>
                        <td>{{ $ticketType->min_per_booking }} - {{ $ticketType->max_per_booking ?: 'No max' }}</td>
                        <td>
                            <form method="post" action="{{ route('event-installments.events.ticket-types.destroy', [$event, $ticketType]) }}">
                                @csrf @method('DELETE')
                                <button class="danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <form method="post" action="{{ route('event-installments.events.ticket-types.store', $event) }}" class="grid-3" style="margin-top:14px">
            @csrf
            <div><label>Name</label><input name="name" required></div>
            <div><label>SKU</label><input name="sku"></div>
            <div><label>Currency</label><input name="currency" value="{{ config('event-installments.payments.currency', 'USD') }}" maxlength="3" required></div>
            <div><label>Price</label><input name="price" type="number" min="0" step="0.01" required></div>
            <div><label>Capacity</label><input name="capacity" type="number" min="1"></div>
            <div><label>Min Per Booking</label><input name="min_per_booking" type="number" min="1" value="1"></div>
            <div><label>Max Per Booking</label><input name="max_per_booking" type="number" min="1"></div>
            <div class="actions" style="align-self:end"><button type="submit">Add Ticket Type</button></div>
        </form>
    </div>

    <div class="panel">
        <h2>Payment Plans</h2>
        <table>
            <thead><tr><th>Name</th><th>Deposit</th><th>Installments</th><th>Issue Ticket</th><th></th></tr></thead>
            <tbody>
                @foreach($event->paymentPlans as $paymentPlan)
                    <tr>
                        <td>{{ $paymentPlan->name }}<div class="muted">{{ $paymentPlan->public_id }}</div></td>
                        <td>{{ $paymentPlan->deposit_type }} {{ $paymentPlan->deposit_value }}</td>
                        <td>{{ $paymentPlan->installment_count }} every {{ $paymentPlan->interval_days }} days</td>
                        <td>{{ $paymentPlan->ticket_issue_policy }}</td>
                        <td>
                            <form method="post" action="{{ route('event-installments.events.payment-plans.destroy', [$event, $paymentPlan]) }}">
                                @csrf @method('DELETE')
                                <button class="danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <form method="post" action="{{ route('event-installments.events.payment-plans.store', $event) }}" class="grid-3" style="margin-top:14px">
            @csrf
            <div><label>Name</label><input name="name" required></div>
            <div><label>Currency</label><input name="currency" value="{{ config('event-installments.payments.currency', 'USD') }}" maxlength="3" required></div>
            <div><label>Deposit Type</label><select name="deposit_type"><option value="percentage">Percentage</option><option value="fixed">Fixed</option></select></div>
            <div><label>Deposit Value</label><input name="deposit_value" type="number" min="0" step="0.01" value="50" required></div>
            <div><label>Installments</label><input name="installment_count" type="number" min="1" max="24" value="2" required></div>
            <div><label>Interval Days</label><input name="interval_days" type="number" min="1" value="30" required></div>
            <div><label>Grace Days</label><input name="grace_days" type="number" min="0" value="3" required></div>
            <div><label>Issue Ticket</label><select name="ticket_issue_policy"><option value="paid_in_full">Paid in full</option><option value="deposit_paid">After deposit</option><option value="manual">Manual</option></select></div>
            <div class="actions" style="align-self:end"><button type="submit">Add Payment Plan</button></div>
        </form>
    </div>
@endsection
