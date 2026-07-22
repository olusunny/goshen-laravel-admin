<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Requests;

class ReopenPrayerSessionRequest extends PrayerAttendanceRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:500']];
    }
}
