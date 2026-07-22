<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Requests;

class StaffConfirmationRequest extends PrayerAttendanceRequest
{
    public function rules(): array
    {
        return [
            'ticket_identifier' => ['required_without:ticket_code', 'nullable', 'string', 'max:120'],
            'ticket_code' => ['required_without:ticket_identifier', 'nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', 'in:staff_ticket_scan,staff_manual_lookup'],
        ];
    }
}
