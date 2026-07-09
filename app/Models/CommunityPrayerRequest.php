<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPrayerRequest extends Model
{
    public const AUTO_HIDE_FLAG_THRESHOLD = 3;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'hidden_at' => 'datetime',
        'moderated_at' => 'datetime',
        'is_anonymous' => 'boolean',
    ];

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityPrayerRequestComment::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(CommunityPrayerRequestFlag::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(CommunityPrayerCommentSuggestion::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->whereNull('hidden_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function hide(string $reason, ?int $moderatorId = null): void
    {
        $this->forceFill([
            'hidden_at' => now(),
            'hidden_reason' => $reason,
            'moderated_by' => $moderatorId,
            'moderated_at' => $moderatorId ? now() : $this->moderated_at,
        ])->save();
    }

    public function unhide(?int $moderatorId = null): void
    {
        $this->forceFill([
            'hidden_at' => null,
            'hidden_reason' => null,
            'moderated_by' => $moderatorId,
            'moderated_at' => $moderatorId ? now() : $this->moderated_at,
        ])->save();
    }
}
