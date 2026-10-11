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

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if ($record->exists && in_array($record->status, ['paused', 'cancelled'], true)
                && array_diff(array_keys($record->getDirty()), ['status', 'updated_at']) === []) {
                return;
            }
            $record->wallet()->firstOrFail()->assertCurrency($record->currency);
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(GoshenWallet::class, 'wallet_id');
    }
}
