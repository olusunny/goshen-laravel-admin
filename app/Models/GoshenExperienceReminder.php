<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenExperienceReminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'sent_count' => 'integer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(GoshenExperienceSurvey::class, 'survey_id');
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }
}
