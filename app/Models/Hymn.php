<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hymn extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean', 'source_pages' => 'array', 'source_warnings' => 'array',
        'reviewed_at' => 'datetime', 'numeric_order' => 'integer',
    ];
}
