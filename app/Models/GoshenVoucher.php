<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Personal\EventInstallments\Models\Event;

class GoshenVoucher extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_EXHAUSTED = 'exhausted';

    public const STATUS_VOID = 'void';

    public const PURPOSE_PAYMENTS = 'payments';

    public const PURPOSE_WALLET_FUNDING = 'wallet_funding';

    public const REDEMPTION_FIXED = 'fixed';

    public const REDEMPTION_POOL = 'pool';

    protected $guarded = [];

    protected $casts = [
        'purpose' => 'string',
        'redemption_type' => 'string',
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getRedemptionCodeAttribute(): ?string
    {
        $encrypted = $this->attributes['encrypted_code'] ?? null;
        if (! filled($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRedemptionCodeAttribute(?string $value): void
    {
        if (filled($value)) {
            $this->attributes['encrypted_code'] = Crypt::encryptString((string) $value);
        }
    }

    public static function purposeOptions(): array
    {
        return [
            self::PURPOSE_PAYMENTS => 'For Payments',
            self::PURPOSE_WALLET_FUNDING => 'Wallet Funding',
        ];
    }

    public static function redemptionTypeOptions(): array
    {
        return [
            self::REDEMPTION_FIXED => 'Fixed amount voucher',
            self::REDEMPTION_POOL => 'Pool balance voucher',
        ];
    }

    public function isPoolVoucher(): bool
    {
        return $this->redemption_type === self::REDEMPTION_POOL;
    }

    public function availableAmount(): float
    {
        if ($this->isPoolVoucher()) {
            return round((float) ($this->remaining_amount ?? 0), 2);
        }

        return (int) $this->used_count >= (int) $this->max_uses
            ? 0.0
            : round((float) $this->amount, 2);
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
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->whereColumn('used_count', '<', 'max_uses')
                            ->where(function (Builder $query): void {
                                $query
                                    ->whereNull('redemption_type')
                                    ->orWhere('redemption_type', self::REDEMPTION_FIXED);
                            });
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('redemption_type', self::REDEMPTION_POOL)
                            ->whereColumn('used_count', '<', 'max_uses')
                            ->where('remaining_amount', '>', 0);
                    });
            })
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }
}
