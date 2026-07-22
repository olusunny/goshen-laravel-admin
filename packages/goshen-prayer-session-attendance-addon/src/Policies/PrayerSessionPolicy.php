<?php

namespace ChurchTools\GoshenPrayerAttendance\Policies;

use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendancePermissionGate;
use Illuminate\Database\Eloquent\Model;

class PrayerSessionPolicy
{
    public function view(Model $user, PrayerSession $session): bool
    {
        return app(PrayerAttendancePermissionGate::class)->allows($user, 'view');
    }

    public function coordinate(Model $user, PrayerSession $session): bool
    {
        return app(PrayerAttendancePermissionGate::class)->allows($user, 'coordinate');
    }

    public function correct(Model $user, PrayerSession $session): bool
    {
        return app(PrayerAttendancePermissionGate::class)->allows($user, 'correct');
    }
}
