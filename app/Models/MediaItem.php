<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class MediaItem extends Model
{
    protected $guarded = [];

    protected $appends = [
        'source_url',
        'cover_photo_url',
        'hd_source_url',
        'sd_source_url',
        'audio_source_url',
    ];

    protected $casts = [
        'can_download' => 'boolean',
        'can_preview' => 'boolean',
        'is_free' => 'boolean',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function comments()
    {
        return $this->morphMany(UserComment::class, 'commentable');
    }

    public function subCategory()
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function getSourceUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->source);
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->cover_photo);
    }

    public function getHdSourceUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->hd_source);
    }

    public function getSdSourceUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->sd_source);
    }

    public function getAudioSourceUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->audio_source);
    }
}
