<?php

namespace Sunny\Fundraising\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CampaignContribution extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    protected $table = 'fundraising_campaign_contributions';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'metadata' => 'array',
        'succeeded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (CampaignContribution $contribution): void {
            if ($contribution->wasSucceededBeforeCurrentChange()) {
                throw new AuthorizationException(static::succeededLockMessage());
            }
        });

        static::deleting(function (CampaignContribution $contribution): void {
            if ($contribution->wasSucceededBeforeCurrentChange()) {
                throw new AuthorizationException(static::succeededLockMessage());
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSucceeded(): bool
    {
        return static::hasSucceededState($this->status, $this->succeeded_at);
    }

    public function wasSucceededBeforeCurrentChange(): bool
    {
        return static::hasSucceededState(
            $this->getOriginal('status'),
            $this->getOriginal('succeeded_at'),
        );
    }

    public static function succeededLockMessage(): string
    {
        return 'Succeeded fundraising contributions are locked and cannot be edited or deleted.';
    }

    private static function hasSucceededState(mixed $status, mixed $succeededAt): bool
    {
        return strtolower((string) $status) === self::STATUS_SUCCEEDED
            || filled($succeededAt);
    }
}
