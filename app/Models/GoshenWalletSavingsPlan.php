<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenWalletSavingsPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'next_charge_at' => 'datetime',
        'last_charge_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(GoshenWallet::class, 'wallet_id');
    }
}
