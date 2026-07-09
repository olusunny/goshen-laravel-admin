<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    protected $guarded = [];

    protected $appends = ['image_url'];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->image_path);
    }
}
