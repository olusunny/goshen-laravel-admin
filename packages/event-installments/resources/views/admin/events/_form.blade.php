@php
    $isEdit = $event->exists;
@endphp

@csrf
@if($isEdit)
    @method('PUT')
@endif

<div class="panel">
    <h2>Event Details</h2>
    <div class="grid">
        <div>
            <label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name', $event->name) }}" required>
        </div>
        <div>
            <label for="slug">Slug</label>
            <input id="slug" name="slug" value="{{ old('slug', $event->slug) }}">
        </div>
        <div>
            <label for="type">Type</label>
            <select id="type" name="type" required>
                @foreach(\Personal\EventInstallments\Enums\EventType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $event->type?->value ?? $event->type) === $type->value)>{{ str_replace('_', ' ', ucfirst($type->value)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status" required>
                @foreach(['draft', 'published', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $event->status) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="timezone">Timezone</label>
            <input id="timezone" name="timezone" value="{{ old('timezone', $event->timezone ?: config('app.timezone', 'UTC')) }}" required>
        </div>
        <div>
            <label for="support_email">Support Email</label>
            <input id="support_email" name="support_email" type="email" value="{{ old('support_email', $event->support_email) }}">
        </div>
        <div>
            <label for="sales_start_at">Sales Start</label>
            <input id="sales_start_at" name="sales_start_at" type="datetime-local" value="{{ old('sales_start_at', optional($event->sales_start_at)->format('Y-m-d\TH:i')) }}">
        </div>
        <div>
            <label for="sales_end_at">Sales End</label>
            <input id="sales_end_at" name="sales_end_at" type="datetime-local" value="{{ old('sales_end_at', optional($event->sales_end_at)->format('Y-m-d\TH:i')) }}">
        </div>
    </div>
</div>

<div class="panel">
    <h2>Venue</h2>
    <div class="grid">
        <div>
            <label for="venue_name">Venue Name</label>
            <input id="venue_name" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}">
        </div>
        <div>
            <label for="venue_address">Venue Address</label>
            <input id="venue_address" name="venue_address" value="{{ old('venue_address', $event->venue_address) }}">
        </div>
    </div>
</div>

<div class="panel">
    <h2>Description</h2>
    <textarea name="description">{{ old('description', $event->description) }}</textarea>
</div>

<div class="actions">
    <button type="submit">{{ $isEdit ? 'Save Event' : 'Create Event' }}</button>
    <a class="button secondary" href="{{ route('event-installments.events.index') }}">Cancel</a>
</div>
