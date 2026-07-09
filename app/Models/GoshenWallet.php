<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoshenWallet extends Model
{
    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'goal_amount' => 'decimal:2',
        'goal_target_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'mobile_user_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(GoshenWalletLedgerEntry::class, 'wallet_id');
    }

    public function savingsPlans(): HasMany
    {
        return $this->hasMany(GoshenWalletSavingsPlan::class, 'wallet_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(GoshenWalletGoal::class, 'wallet_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(GoshenWalletWithdrawalRequest::class, 'wallet_id');
    }
}
