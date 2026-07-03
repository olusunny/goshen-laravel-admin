<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class ChurchEvent extends Model
{
    protected $guarded = [];

    protected $appends = ['thumbnail_url', 'portrait_image_url'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_published' => 'boolean',
        'is_pilgrimage' => 'boolean',
        'live_streaming_platforms' => 'array',
        'invited_gospel_musicians' => 'array',
        'event_schedule' => 'array',
        'pilgrimage_details' => 'array',
    ];

    public function getThumbnailUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->thumbnail);
    }

    public function getPortraitImageUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->portrait_image);
    }
}
