<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenQuizWinner extends Model
{
    public const WALLET_PRIZE_NOT_CONFIGURED = 'not_configured';
    public const WALLET_PRIZE_PENDING = 'pending';
    public const WALLET_PRIZE_PAID = 'paid';

    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'elapsed_seconds' => 'integer',
        'selected_at' => 'datetime',
        'wallet_prize_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(GoshenQuiz::class, 'quiz_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(GoshenQuizAttempt::class, 'attempt_id');
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function walletSponsor(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'wallet_sponsor_mobile_user_id');
    }

    public function walletLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(GoshenWalletLedgerEntry::class, 'wallet_ledger_entry_id');
    }
}
