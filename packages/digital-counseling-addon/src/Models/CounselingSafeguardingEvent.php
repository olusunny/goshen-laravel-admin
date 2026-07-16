<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingSafeguardingEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CounselingCase::class, 'case_id');
    }
}
