<?php

namespace ChurchTools\DigitalHymnBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HymnBook extends Model
{
    protected $guarded = [];

    public function sections(): HasMany
    {
        return $this->hasMany(HymnSection::class);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(HymnRelease::class, 'published_release_id');
    }
}
