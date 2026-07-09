<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class PaymentPlan extends Model
{
    use HasPublicId;

    protected $table = 'ei_payment_plans';

    protected $fillable = [
        'event_id',
        'name',
        'currency',
        'deposit_type',
        'deposit_value',
        'installment_count',
        'interval_days',
        'grace_days',
        'ticket_issue_policy',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'deposit_value' => 'decimal:2',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
