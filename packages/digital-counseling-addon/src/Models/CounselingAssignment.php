<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingAssignment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CounselingCase::class, 'case_id');
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(CounselingProviderProfile::class, 'provider_profile_id');
    }
}
