<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class PaymentInstallment extends Model
{
    use HasPublicId;

    protected $table = 'ei_payment_installments';

    protected $fillable = [
        'booking_id',
        'sequence',
        'currency',
        'amount',
        'paid_amount',
        'due_on',
        'paid_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_on' => 'date',
        'paid_at' => 'datetime',
        'status' => InstallmentStatus::class,
        'metadata' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'installment_id');
    }
}
