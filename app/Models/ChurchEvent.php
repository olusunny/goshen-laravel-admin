<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class ChurchEvent extends Model
{
    public const RECURRENCE_NONE = 'none';

    public const RECURRENCE_WEEKLY = 'weekly';

    public const RECURRENCE_MONTHLY_NTH_WEEKDAY = 'monthly_nth_weekday';

    protected $guarded = [];

    protected $appends = ['thumbnail_url', 'portrait_image_url'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_published' => 'boolean',
        'is_pilgrimage' => 'boolean',
        'live_streaming_platforms' => 'array',
        'invited_gospel_musicians' => 'array',
        'event_schedule' => 'array',
        'pilgrimage_details' => 'array',
        'recurrence_interval' => 'integer',
        'recurrence_weekday' => 'integer',
        'recurrence_week_of_month' => 'integer',
        'recurrence_until' => 'date',
    ];

    public function isRecurring(): bool
    {
        return in_array($this->recurrence_type, [
            self::RECURRENCE_WEEKLY,
            self::RECURRENCE_MONTHLY_NTH_WEEKDAY,
        ], true);
    }

    public function recurrenceLabel(): string
    {
        return match ($this->recurrence_type) {
            self::RECURRENCE_WEEKLY => 'Every '.$this->weekdayLabel(),
            self::RECURRENCE_MONTHLY_NTH_WEEKDAY => $this->weekOfMonthLabel().' '.$this->weekdayLabel().' of the month',
            default => 'One-time event',
        };
    }

    public static function recurrenceOptions(): array
    {
        return [
            self::RECURRENCE_NONE => 'One-time event',
            self::RECURRENCE_WEEKLY => 'Weekly Programme',
            self::RECURRENCE_MONTHLY_NTH_WEEKDAY => 'Monthly Programme',
        ];
    }

    public static function weekdayOptions(): array
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }

    public static function weekOfMonthOptions(): array
    {
        return [
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            -1 => 'Last',
        ];
    }

    private function weekdayLabel(): string
    {
        return self::weekdayOptions()[(int) ($this->recurrence_weekday ?? 0)] ?? 'Sunday';
    }

    private function weekOfMonthLabel(): string
    {
        return self::weekOfMonthOptions()[(int) ($this->recurrence_week_of_month ?? 1)] ?? '1st';
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->thumbnail);
    }

    public function getPortraitImageUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->portrait_image);
    }
}
