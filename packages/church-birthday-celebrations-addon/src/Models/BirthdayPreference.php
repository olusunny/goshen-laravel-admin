<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayPreference extends Model
{
    protected $table = 'birthday_celebration_preferences';
    protected $guarded = [];
    protected $attributes = [
        'visibility_enabled' => true,
        'greetings_enabled' => true,
        'use_profile_photo' => true,
    ];
    protected $casts = ['visibility_enabled' => 'boolean', 'greetings_enabled' => 'boolean', 'use_profile_photo' => 'boolean'];

    public function member(): BelongsTo { return $this->belongsTo(config('church-birthday-celebrations.models.mobile_user'), 'mobile_user_id'); }
    public function preferredVerse(): BelongsTo { return $this->belongsTo(BirthdayVerse::class, 'preferred_verse_id'); }
    public function preferredTemplate(): BelongsTo { return $this->belongsTo(BirthdayTemplate::class, 'preferred_template_id'); }
}
