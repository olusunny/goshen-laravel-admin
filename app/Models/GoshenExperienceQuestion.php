<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoshenExperienceQuestion extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_RATING = 'rating';
    public const TYPE_CHOICE = 'choice';
    public const TYPE_MULTI_CHOICE = 'multi_choice';
    public const TYPE_IMAGE_CHOICE = 'image_choice';
    public const TYPE_COLOR_CHOICE = 'color_choice';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'conditional_logic' => 'array',
        'settings' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(GoshenExperienceSurvey::class, 'survey_id');
    }
}
