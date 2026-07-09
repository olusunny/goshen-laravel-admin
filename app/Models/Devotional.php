<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class Devotional extends Model
{
    protected $guarded = [];

    protected $appends = ['thumbnail_url'];

    protected $casts = ['date' => 'date', 'is_published' => 'boolean'];

    public function getThumbnailUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->thumbnail);
    }
}
