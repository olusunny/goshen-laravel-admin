<?php

namespace ChurchTools\GoshenPrayerAttendance\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerAttendanceDelivery extends Model
{
    public const KIND_ACTIVATION = 'activation';
    public const KIND_REMINDER = 'reminder';

    protected $table = 'prayer_attendance_notification_deliveries';

    protected $guarded = [];

    protected $casts = ['claimed_at' => 'datetime', 'sent_at' => 'datetime'];
}
