@extends('event-installments::admin.layout')

@section('title', 'Events')

@section('actions')
    <a class="button" href="{{ route('event-installments.events.create') }}">New Event</a>
@endsection

@section('content')
    <div class="panel">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Sales</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>
                            <a href="{{ route('event-installments.events.show', $event) }}"><strong>{{ $event->name }}</strong></a>
                            <div class="muted">{{ $event->public_id }}</div>
                        </td>
                        <td>{{ $event->type?->value ?? $event->type }}</td>
                        <td>{{ $event->status }}</td>
                        <td>
                            <div>{{ optional($event->sales_start_at)->format('Y-m-d H:i') ?: 'Open' }}</div>
                            <div class="muted">{{ optional($event->sales_end_at)->format('Y-m-d H:i') ?: 'No close date' }}</div>
                        </td>
                        <td class="actions">
                            <a class="button secondary" href="{{ route('event-installments.events.edit', $event) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $events->links() }}
@endsection
