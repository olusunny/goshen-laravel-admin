<?php

namespace ChurchTools\GoshenPrayerAttendance\Services;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class PrayerAttendancePermissionGate
{
    public function allows(?Model $user, string $ability): bool
    {
        if (! $user) {
            return false;
        }

        try {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }

            $permission = config("prayer-attendance.permissions.{$ability}");
            $adminPermission = config('prayer-attendance.permissions.admin');

            if (! method_exists($user, 'hasPermissionTo')) {
                return false;
            }

            // Check the broad add-on administrator grant first: Spatie throws
            // when an optional action permission has not been seeded yet.
            if (is_string($adminPermission) && $adminPermission !== '' && $this->hasPermission($user, $adminPermission)) {
                return true;
            }

            return is_string($permission) && $permission !== '' && $this->hasPermission($user, $permission);
        } catch (Throwable) {
            return false;
        }
    }

    public function authorize(?Model $user, string $ability): void
    {
        abort_unless($this->allows($user, $ability), 403, 'You are not permitted to perform this prayer attendance action.');
    }

    private function hasPermission(Model $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (Throwable) {
            return false;
        }
    }
}
