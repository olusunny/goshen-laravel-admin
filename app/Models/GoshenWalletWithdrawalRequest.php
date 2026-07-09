<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenWalletWithdrawalRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(GoshenWallet::class, 'wallet_id');
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'mobile_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'reviewed_by_mobile_user_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(GoshenWalletLedgerEntry::class, 'ledger_entry_id');
    }

    public function refundLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(GoshenWalletLedgerEntry::class, 'refund_ledger_entry_id');
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }
}
