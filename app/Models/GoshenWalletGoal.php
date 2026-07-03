<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenWalletGoal extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    protected $guarded = [];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'target_at' => 'datetime',
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(GoshenWallet::class, 'wallet_id');
    }
}
