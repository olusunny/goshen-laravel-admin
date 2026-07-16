<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;

class CounselingCountryResource extends Model
{
    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'review_after' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
