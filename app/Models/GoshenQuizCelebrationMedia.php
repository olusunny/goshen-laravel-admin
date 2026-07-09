<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoshenQuizCelebrationMedia extends Model
{
    protected $table = 'goshen_quiz_celebration_media';

    protected $guarded = [];

    protected $casts = [
        'image_paths' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(GoshenQuiz::class, 'celebration_media_id');
    }
}
