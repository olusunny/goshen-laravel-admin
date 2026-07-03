<?php

namespace App\Models;

use App\Services\AccommodationNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationBooking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'check_in_date' => 'date',
        'checkout_date' => 'date',
        'price_per_night' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'rules_accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccommodationCategory::class, 'accommodation_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'accommodation_unit_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccommodationPayment::class, 'booking_id');
    }

    protected static function booted(): void
    {
        static::updated(function (AccommodationBooking $booking): void {
            if (! $booking->wasChanged('booking_status')) {
                return;
            }

            if ($booking->booking_status === 'confirmed') {
                app(AccommodationNotificationService::class)->bookingConfirmed($booking);
            }

            if ($booking->booking_status === 'cancelled') {
                app(AccommodationNotificationService::class)->bookingCancelled($booking);
            }
        });
    }
}
