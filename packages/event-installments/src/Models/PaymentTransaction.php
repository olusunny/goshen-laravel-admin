<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class PaymentTransaction extends Model
{
    use HasPublicId;

    protected $table = 'ei_payment_transactions';

    protected $fillable = [
        'booking_id',
        'installment_id',
        'gateway',
        'provider_reference',
        'provider_event_id',
        'currency',
        'amount',
        'status',
        'paid_at',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'payload' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(PaymentInstallment::class, 'installment_id');
    }
}
