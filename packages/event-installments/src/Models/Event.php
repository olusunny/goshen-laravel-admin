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
        'settings',
    ];

    protected $casts = [
        'type' => EventType::class,
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
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
}
