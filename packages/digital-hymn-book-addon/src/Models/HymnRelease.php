<?php

namespace ChurchTools\DigitalHymnBook\Models;

use Illuminate\Database\Eloquent\Model;

class HymnRelease extends Model
{
    protected $guarded = [];

    protected $casts = ['counts' => 'array', 'book_metadata' => 'array', 'section_metadata' => 'array'];
}
