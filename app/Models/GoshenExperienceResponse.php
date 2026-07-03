<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\Ticket;

class GoshenExperienceResponse extends Model
{
    protected $guarded = [];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'audio_duration_seconds' => 'integer',
        'video_duration_seconds' => 'integer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(GoshenExperienceSurvey::class, 'survey_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
