<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Requests;

class SelfConfirmationRequest extends PrayerAttendanceRequest
{
    public function rules(): array
    {
        return [
            'qr_token' => ['required_without:qr_payload', 'nullable', 'string', 'max:512'],
            'qr_payload' => ['required_without:qr_token', 'nullable', 'string', 'max:512'],
            'ticket_identifier' => ['nullable', 'string', 'max:120'],
            'ticket_code' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
