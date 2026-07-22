<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Requests;

class StaffSyncRequest extends PrayerAttendanceRequest
{
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.idempotency_key' => ['required', 'string', 'max:120'],
            'records.*.session_id' => ['required', 'string', 'max:64'],
            // The controller accepts either name for compatibility and rejects a
            // record only when both are blank, preserving partial batch results.
            'records.*.ticket_identifier' => ['nullable', 'string', 'max:120'],
            'records.*.ticket_code' => ['nullable', 'string', 'max:120'],
            'records.*.created_at' => ['nullable', 'date'],
        ];
    }
}
