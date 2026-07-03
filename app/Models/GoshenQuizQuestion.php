<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenQuizQuestion extends Model
{
    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_MULTI_CHOICE = 'multi_choice';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_SHORT_TEXT = 'short_text';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'points' => 'decimal:2',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(GoshenQuiz::class, 'quiz_id');
    }
}
