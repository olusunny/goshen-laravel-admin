<?php

namespace ChurchTools\CloudBackup\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudBackupSchedule extends Model
{
    protected $table = 'cloud_backup_schedules';

    protected $fillable = [
        'connection_id',
        'name',
        'frequency',
        'enabled',
        'include_files',
        'include_database',
        'source_path',
        'database_connection',
        'exclude_paths',
        'retention_count',
        'schedule_time',
        'schedule_weekday',
        'schedule_month_day',
        'next_run_at',
        'last_run_at',
        'timezone',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'include_files' => 'boolean',
        'include_database' => 'boolean',
        'exclude_paths' => 'array',
        'schedule_weekday' => 'integer',
        'schedule_month_day' => 'integer',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CloudBackupConnection::class, 'connection_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CloudBackupRun::class, 'schedule_id');
    }

    public function calculateNextRun(?CarbonImmutable $from = null): CarbonImmutable
    {
        $timezone = $this->timezone ?: 'Africa/Lagos';
        $from = ($from ?: CarbonImmutable::now($timezone))->setTimezone($timezone);

        return match ($this->frequency) {
            'hourly' => $from->addHour()->setTimezone(config('app.timezone', 'UTC')),
            'weekly' => $this->nextWeeklyRun($from)->setTimezone(config('app.timezone', 'UTC')),
            'monthly' => $this->nextMonthlyRun($from)->setTimezone(config('app.timezone', 'UTC')),
            default => $this->nextDailyRun($from)->setTimezone(config('app.timezone', 'UTC')),
        };
    }

    private function nextDailyRun(CarbonImmutable $from): CarbonImmutable
    {
        [$hour, $minute] = $this->scheduleTimeParts();
        $candidate = $from->setTime($hour, $minute);

        return $candidate->lessThanOrEqualTo($from) ? $candidate->addDay() : $candidate;
    }

    private function nextWeeklyRun(CarbonImmutable $from): CarbonImmutable
    {
        [$hour, $minute] = $this->scheduleTimeParts();
        $weekday = max(0, min(6, (int) ($this->schedule_weekday ?? 0)));
        $candidate = $from->setTime($hour, $minute);
        $daysUntil = ($weekday - $candidate->dayOfWeek + 7) % 7;
        $candidate = $candidate->addDays($daysUntil);

        return $candidate->lessThanOrEqualTo($from) ? $candidate->addWeek() : $candidate;
    }

    private function nextMonthlyRun(CarbonImmutable $from): CarbonImmutable
    {
        [$hour, $minute] = $this->scheduleTimeParts();
        $day = max(1, min(28, (int) ($this->schedule_month_day ?? 1)));
        $candidate = $from->setDay($day)->setTime($hour, $minute);

        return $candidate->lessThanOrEqualTo($from)
            ? $candidate->addMonthNoOverflow()->setDay($day)->setTime($hour, $minute)
            : $candidate;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function scheduleTimeParts(): array
    {
        $time = is_string($this->schedule_time) && preg_match('/^\d{2}:\d{2}/', $this->schedule_time)
            ? $this->schedule_time
            : '00:00';

        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return [max(0, min(23, $hour)), max(0, min(59, $minute))];
    }
}
