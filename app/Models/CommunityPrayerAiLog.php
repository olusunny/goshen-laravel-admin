<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityPrayerAiLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'output' => 'array',
    ];
}
