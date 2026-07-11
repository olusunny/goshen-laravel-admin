<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenTransactionEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'counts_toward_revenue' => 'boolean',
        'initiated_at' => 'datetime',
        'settled_at' => 'datetime',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'mobile_user_id');
    }
}
