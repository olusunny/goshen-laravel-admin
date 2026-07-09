<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (Donation $donation): void {
            if ($donation->wasCompletedBeforeCurrentChange()) {
                throw new AuthorizationException(static::completedLockMessage());
            }
        });

        static::deleting(function (Donation $donation): void {
            if ($donation->wasCompletedBeforeCurrentChange()) {
                throw new AuthorizationException(static::completedLockMessage());
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(DonationCategory::class, 'donation_category_id');
    }

    public function isCompleted(): bool
    {
        return static::hasCompletedState($this->status, $this->paid_at);
    }

    public function wasCompletedBeforeCurrentChange(): bool
    {
        return static::hasCompletedState(
            $this->getOriginal('status'),
            $this->getOriginal('paid_at'),
        );
    }

    public static function completedLockMessage(): string
    {
        return 'Completed giving records are locked and cannot be edited or deleted.';
    }

    private static function hasCompletedState(mixed $status, mixed $paidAt): bool
    {
        return in_array(strtolower((string) $status), ['paid', 'success', 'completed'], true)
            || filled($paidAt);
    }
}
