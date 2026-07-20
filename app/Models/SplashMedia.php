<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplashMedia extends Model
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    protected $table = 'splash_media';

    protected $guarded = [];

    protected $appends = [
        'media_url',
        'thumbnail_url',
        'preview_url',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'active' => 'boolean',
        'is_default' => 'boolean',
        'version' => 'integer',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_ms' => 'integer',
        'activated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SplashMedia $media): void {
            if (blank($media->version)) {
                $media->version = ((int) static::query()->max('version')) + 1;
            }

            if (! static::query()->where('active', true)->exists()) {
                $media->active = true;
                $media->activated_at ??= now();
            }
        });

        static::saving(function (SplashMedia $media): void {
            if (
                $media->exists
                && $media->getOriginal('active')
                && ! $media->active
                && ! static::query()->whereKeyNot($media->getKey())->where('active', true)->exists()
            ) {
                $media->active = true;
            }
        });

        static::saved(function (SplashMedia $media): void {
            if ($media->active) {
                static::withoutEvents(fn () => static::query()
                    ->whereKeyNot($media->getKey())
                    ->update(['active' => false]));
            }

            if ($media->is_default) {
                static::withoutEvents(fn () => static::query()
                    ->whereKeyNot($media->getKey())
                    ->update(['is_default' => false]));
            }
        });

        static::deleted(function (SplashMedia $media): void {
            if (! $media->active || static::query()->where('active', true)->exists()) {
                return;
            }

            $replacement = static::query()
                ->orderByDesc('is_default')
                ->orderByDesc('version')
                ->first();

            $replacement?->forceFill([
                'active' => true,
                'activated_at' => now(),
            ])->save();
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_id');
    }

    public function getMediaUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->media_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (filled($this->thumbnail_path)) {
            return MediaUrl::resolve($this->thumbnail_path);
        }

        if ($this->media_type === self::TYPE_IMAGE) {
            return MediaUrl::resolve($this->media_path);
        }

        return null;
    }

    public function getPreviewUrlAttribute(): ?string
    {
        return $this->thumbnail_url ?? $this->media_url;
    }
}
