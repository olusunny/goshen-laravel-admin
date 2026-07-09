<?php

namespace Sunny\Fundraising\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Campaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'fundraising_campaigns';

    protected $guarded = [];

    protected $casts = [
        'goal_amount' => 'decimal:2',
        'raised_amount' => 'decimal:2',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'auto_stop_when_goal_reached' => 'boolean',
        'show_recent_contributors' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $campaign): void {
            if (blank($campaign->slug)) {
                $campaign->slug = Str::slug($campaign->title);
            }

            $campaign->currency = static::normalizeCurrency($campaign->currency);
            $campaign->validateState();
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function media(): HasMany
    {
        return $this->hasMany(CampaignMedia::class, 'campaign_id')->orderBy('sort_order')->orderBy('id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(CampaignContribution::class, 'campaign_id');
    }

    public function successfulContributions(): HasMany
    {
        return $this->contributions()->where('status', CampaignContribution::STATUS_SUCCEEDED);
    }

    public function canContribute(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->start_at && $this->start_at->isFuture()) {
            return false;
        }

        if ($this->end_at && $this->end_at->isPast()) {
            return false;
        }

        if ($this->auto_stop_when_goal_reached && $this->goalReached()) {
            return false;
        }

        return true;
    }

    public function publishNow(): bool
    {
        $now = now();
        $startAt = $this->start_at;

        return $this->forceFill([
            'status' => self::STATUS_ACTIVE,
            'start_at' => (! $startAt || $startAt->isFuture()) ? $now : $startAt,
        ])->save();
    }

    public function ctaLabel(): string
    {
        $label = trim((string) data_get($this->metadata, 'cta_label'));

        return $label !== '' ? $label : 'Support this campaign';
    }

    public function goalReached(): bool
    {
        return (float) $this->raised_amount + 0.01 >= (float) $this->goal_amount;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    public function hasStarted(): bool
    {
        return ! $this->start_at || $this->start_at->lte(now());
    }

    public function isExpired(): bool
    {
        return $this->end_at && $this->end_at->lte(now());
    }

    public function shouldAutoStop(): bool
    {
        return (bool) $this->auto_stop_when_goal_reached;
    }

    public function progressPercentage(): float
    {
        $goal = (float) $this->goal_amount;
        if ($goal <= 0) {
            return 0;
        }

        return round(min(100, max(0, ((float) $this->raised_amount / $goal) * 100)), 2);
    }

    public function remainingSeconds(): ?int
    {
        if (! $this->end_at) {
            return null;
        }

        return max(0, now()->diffInSeconds($this->end_at, false));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $this->scopeActive($query);
    }

    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query
            ->active()
            ->where(function (Builder $window): void {
                $window->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function (Builder $window): void {
                $window->whereNull('end_at')->orWhere('end_at', '>', now());
            });
    }

    public function scopeOpenForContributions(Builder $query): Builder
    {
        return $query->currentlyVisible();
    }

    private function validateState(): void
    {
        $errors = [];

        if (blank($this->title)) {
            $errors['title'] = 'Campaign title is required.';
        }

        if (blank($this->cause)) {
            $errors['cause'] = 'Campaign cause is required.';
        }

        if ((float) $this->goal_amount <= 0) {
            $errors['goal_amount'] = 'Campaign goal amount must be greater than zero.';
        }

        if (! preg_match('/^[A-Z]{3}$/', (string) $this->currency)) {
            $errors['currency'] = 'Campaign currency must be a three-letter ISO code such as GBP.';
        }

        if ($this->start_at && $this->end_at && $this->end_at->lte($this->start_at)) {
            $errors['end_at'] = 'Campaign closing date must be after the start date.';
        }

        if ($this->status === self::STATUS_ACTIVE && ! $this->end_at) {
            $errors['end_at'] = 'A closing date is required before publishing a fundraising campaign.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function normalizeCurrency(mixed $currency): string
    {
        $value = strtoupper(trim((string) $currency));

        return match ($value) {
            '£', 'POUND', 'POUNDS', 'STERLING' => 'GBP',
            '$', 'DOLLAR', 'DOLLARS' => 'USD',
            '€', 'EURO', 'EUROS' => 'EUR',
            default => $value,
        };
    }
}
