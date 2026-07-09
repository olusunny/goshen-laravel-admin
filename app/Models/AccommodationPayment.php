<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccommodationPayment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_response' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(AccommodationBooking::class, 'booking_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'user_id');
    }
}
