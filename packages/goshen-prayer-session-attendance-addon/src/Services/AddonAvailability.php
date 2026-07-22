<?php

namespace ChurchTools\GoshenPrayerAttendance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AddonAvailability
{
    public function isActive(): bool
    {
        if (! filter_var(config('prayer-attendance.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            return Schema::hasTable('addons')
                && DB::table('addons')
                    ->where('package_key', config('prayer-attendance.package_key'))
                    ->where('status', 'active')
                    ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
