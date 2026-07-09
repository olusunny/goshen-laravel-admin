<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserComment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'is_reported' => 'boolean',
    ];
}
