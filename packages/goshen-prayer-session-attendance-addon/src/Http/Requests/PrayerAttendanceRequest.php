<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class PrayerAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('data', $this->all());
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        if (is_array($payload)) {
            $this->merge($payload);
        }
    }
}
