<?php

namespace ChurchTools\GoshenPrayerAttendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PrayerSession extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'prayer_attendance_sessions';

    protected $guarded = [];

    protected $casts = [
        'scheduled_starts_at' => 'datetime',
        'scheduled_ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
        'qr_activated_at' => 'datetime',
        'activation_notification_dispatched_at' => 'datetime',
        'reminder_dispatched_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            $session->public_id ??= (string) Str::ulid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(config('prayer-attendance.models.event'), 'event_id');
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(PrayerAttendanceConfirmation::class, 'prayer_session_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PrayerAttendanceAudit::class, 'prayer_session_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
