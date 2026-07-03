<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    protected $guarded = [];

    protected $appends = ['thumbnail_url'];

    protected $casts = ['is_active' => 'boolean'];

    public function getThumbnailUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->thumbnail);
    }
}
