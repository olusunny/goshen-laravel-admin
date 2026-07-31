<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdayVerse extends Model
{
    protected $table = 'birthday_celebration_verses';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}
