<?php

namespace ChurchTools\DigitalHymnBook\Models;

use Illuminate\Database\Eloquent\Model;

class HymnPublicationEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
