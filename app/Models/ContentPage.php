<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'sections' => 'array',
    ];

    protected $appends = ['hero_image_url'];

    public function getHeroImageUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->hero_image);
    }
}
