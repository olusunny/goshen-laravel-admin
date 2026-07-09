<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class Booking extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'ei_bookings';

    protected $fillable = [
        'event_id',
        'payment_plan_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'payment_customer_id',
        'payment_method_id',
        'auto_charge_enabled',
        'auto_charge_failed_at',
        'auto_charge_failure_reason',
        'currency',
        'subtotal',
        'total',
        'paid_total',
        'status',
        'payment_expires_at',
        'payment_reminder_sent_at',
        'cancelled_at',
        'cancelled_by_id',
        'cancellation_reason',
        'metadata',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'auto_charge_enabled' => 'boolean',
        'auto_charge_failed_at' => 'datetime',
        'payment_expires_at' => 'datetime',
        'payment_reminder_sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BookingLine::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentInstallment::class);
    }
}
