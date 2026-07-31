<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BirthdayDelivery extends Model
{
    protected $table = 'birthday_celebration_deliveries';
    protected $guarded = [];
    protected $casts = ['sent_at' => 'datetime', 'last_attempt_at' => 'datetime'];

    public function celebrations(): BelongsToMany
    {
        return $this->belongsToMany(BirthdayCelebration::class, 'birthday_celebration_delivery_links', 'delivery_id', 'celebration_id');
    }
}
