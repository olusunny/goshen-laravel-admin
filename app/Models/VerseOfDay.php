<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VerseOfDay extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public static function current(): ?self
    {
        $todayInNigeria = now('Africa/Lagos')->toDateString();

        return static::query()
            ->published()
            ->whereDate('date', $todayInNigeria)
            ->first();
    }
}
