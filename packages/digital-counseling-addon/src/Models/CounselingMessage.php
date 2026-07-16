<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CounselingMessage extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_IMAGE = 'image';
    public const TYPE_FILE = 'file';

    protected $guarded = [];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'metadata' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CounselingCase::class, 'case_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'actor_type', 'actor_id');
    }
}
