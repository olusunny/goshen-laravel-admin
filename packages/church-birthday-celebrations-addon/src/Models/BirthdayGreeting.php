<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayGreeting extends Model
{
    protected $table = 'birthday_celebration_greetings';
    protected $guarded = [];
    protected $casts = ['reported_at' => 'datetime', 'hidden_at' => 'datetime'];
    public function celebration(): BelongsTo { return $this->belongsTo(BirthdayCelebration::class, 'celebration_id'); }
    public function member(): BelongsTo { return $this->belongsTo(config('church-birthday-celebrations.models.mobile_user'), 'mobile_user_id'); }
    public function reports() { return $this->hasMany(BirthdayReport::class, 'greeting_id'); }
}
