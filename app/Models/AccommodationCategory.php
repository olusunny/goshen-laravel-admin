<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationCategory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gallery_images' => 'array',
        'price' => 'decimal:2',
        'children_allowed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(AccommodationFacility::class, 'accommodation_category_facility');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(AccommodationService::class, 'accommodation_category_service');
    }

    public function units(): HasMany
    {
        return $this->hasMany(AccommodationUnit::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AccommodationBooking::class);
    }
}
