<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingMessage extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_AUDIO = 'audio';

    protected $guarded = [];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'metadata' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CounselingCase::class, 'case_id');
    }
}
