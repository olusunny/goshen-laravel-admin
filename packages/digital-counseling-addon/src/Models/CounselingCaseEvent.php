<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingCaseEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CounselingCase::class, 'case_id');
    }
}
