<?php

namespace ChurchTools\GoshenPrayerAttendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerAttendanceConfirmation extends Model
{
    public const METHOD_SELF_QR = 'attendee_self_qr';
    public const METHOD_STAFF_SCAN = 'staff_ticket_scan';
    public const METHOD_STAFF_LOOKUP = 'staff_manual_lookup';

    protected $table = 'prayer_attendance_confirmations';

    protected $guarded = [];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'voided_at' => 'datetime',
        'source_metadata' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PrayerSession::class, 'prayer_session_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('prayer-attendance.models.ticket'), 'ticket_id');
    }

}
