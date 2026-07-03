<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerPoint extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date', 'is_published' => 'boolean'];
}
