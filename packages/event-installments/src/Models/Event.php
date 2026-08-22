<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class Event extends Model
{
    use HasPublicId;
    use SoftDeletes;

    public const CHECK_IN_MODE_PER_DAY = 'per_day';

    public const CHECK_IN_MODE_EVENT_DURATION = 'event_duration';

    protected $table = 'ei_events';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'type',
        'description',
        'timezone',
        'venue_name',
        'venue_address',
        'support_email',
        'status',
        'sales_start_at',
        'sales_end_at',
        'start_date',
        'end_date',
        'settings',
    ];

    protected $casts = [
        'type' => EventType::class,
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'settings' => 'array',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(EventSchedule::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(EventTicketType::class);
    }

    public function attendeeFields(): HasMany
    {
        return $this->hasMany(EventAttendeeField::class);
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function checkInMode(): string
    {
        $mode = data_get($this->settings, 'check_in.mode');

        return in_array($mode, [self::CHECK_IN_MODE_PER_DAY, self::CHECK_IN_MODE_EVENT_DURATION], true)
            ? $mode
            : self::CHECK_IN_MODE_PER_DAY;
    }

    public function usesEventDurationCheckIn(): bool
    {
        return $this->checkInMode() === self::CHECK_IN_MODE_EVENT_DURATION;
    }

    public function effectiveCheckInDayNumber(int $submittedDayNumber): int
    {
        return $this->usesEventDurationCheckIn() ? 1 : max(1, $submittedDayNumber);
    }

    public function allowsCheckInDayNumber(int $submittedDayNumber): bool
    {
        if ($this->usesEventDurationCheckIn()) {
            return true;
        }

        $dayNumber = max(1, $submittedDayNumber);
        $this->loadMissing('schedules');
        $scheduledDays = $this->schedules
            ->pluck('day_number')
            ->map(fn (mixed $day): int => (int) $day)
            ->filter(fn (int $day): bool => $day > 0)
            ->unique()
            ->values()
            ->all();

        return $scheduledDays === []
            ? $dayNumber === 1
            : in_array($dayNumber, $scheduledDays, true);
    }
}
