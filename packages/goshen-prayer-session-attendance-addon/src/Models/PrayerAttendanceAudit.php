<?php

namespace ChurchTools\GoshenPrayerAttendance\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerAttendanceAudit extends Model
{
    protected $table = 'prayer_attendance_audits';

    protected $guarded = [];

    protected $casts = ['metadata' => 'array'];
}
