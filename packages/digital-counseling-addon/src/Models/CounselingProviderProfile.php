<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;

class CounselingProviderProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'languages' => 'array',
        'metadata' => 'array',
    ];
}
