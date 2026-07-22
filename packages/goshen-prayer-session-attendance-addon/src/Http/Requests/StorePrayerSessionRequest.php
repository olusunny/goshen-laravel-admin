<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Requests;

use Illuminate\Validation\Rule;

class StorePrayerSessionRequest extends PrayerAttendanceRequest
{
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', Rule::exists('ei_events', 'id')],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'scheduled_starts_at' => ['nullable', 'date'],
            'scheduled_ends_at' => ['nullable', 'date', 'after:scheduled_starts_at'],
        ];
    }
}
