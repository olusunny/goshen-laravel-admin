<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hymn extends Model
{
    protected $guarded = [];

    protected $casts = ['is_published' => 'boolean'];
}
