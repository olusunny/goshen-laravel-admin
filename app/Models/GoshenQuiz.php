<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Personal\EventInstallments\Models\Event;

class GoshenQuiz extends Model
{
    public const AUDIENCE_ALL_USERS = 'all_users';
    public const AUDIENCE_GOSHEN_CHECKED_IN = 'goshen_checked_in';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_grade' => 'boolean',
        'auto_select_winners' => 'boolean',
        'track_timing' => 'boolean',
        'timer_seconds' => 'integer',
        'winners_count' => 'integer',
        'show_prize' => 'boolean',
        'wallet_prize_enabled' => 'boolean',
        'wallet_prize_amount' => 'decimal:2',
        'show_winners_immediately' => 'boolean',
        'celebration_enabled' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function celebrationMedia(): BelongsTo
    {
        return $this->belongsTo(GoshenQuizCelebrationMedia::class, 'celebration_media_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(GoshenQuizQuestion::class, 'quiz_id')->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(GoshenQuizAttempt::class, 'quiz_id');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(GoshenQuizWinner::class, 'quiz_id')->orderBy('rank');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('opens_at')->orWhere('opens_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            });
    }
}
