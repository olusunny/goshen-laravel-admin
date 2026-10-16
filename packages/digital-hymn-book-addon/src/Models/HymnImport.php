<?php

namespace ChurchTools\DigitalHymnBook\Models;

use Illuminate\Database\Eloquent\Model;

class HymnImport extends Model
{
    protected $guarded = [];

    protected $casts = ['approval' => 'array', 'summary' => 'array'];
}
