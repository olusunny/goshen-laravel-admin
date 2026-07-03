<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccommodationFacility extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(AccommodationCategory::class, 'accommodation_category_facility');
    }
}
