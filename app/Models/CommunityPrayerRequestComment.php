<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Support\Facades\Storage;

class CommunityPrayerRequestComment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'hidden_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CommunityPrayerRequestComment $comment) {
            if ($comment->audio_path) {
                Storage::disk('public')->delete($comment->audio_path);
            }
        });
    }

    public function prayerRequest(): BelongsTo
    {
        return $this->belongsTo(CommunityPrayerRequest::class, 'community_prayer_request_id');
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }
}
