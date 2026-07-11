<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Personal\EventInstallments\Models\Event;

class GoshenVoucher extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_EXHAUSTED = 'exhausted';

    public const STATUS_VOID = 'void';

    public const PURPOSE_PAYMENTS = 'payments';

    public const PURPOSE_WALLET_FUNDING = 'wallet_funding';

    protected $guarded = [];

    protected $casts = [
        'purpose' => 'string',
        'amount' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function purposeOptions(): array
    {
        return [
            self::PURPOSE_PAYMENTS => 'For Payments',
            self::PURPOSE_WALLET_FUNDING => 'Wallet Funding',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function createdByMobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'created_by_mobile_user_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(GoshenVoucherUsage::class, 'voucher_id');
    }

    public function isUnused(): bool
    {
        return (int) $this->used_count === 0 && ! $this->usages()->exists();
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereColumn('used_count', '<', 'max_uses')
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }
}
