<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Personal\EventInstallments\Models\Event;

class GoshenExperienceSurvey extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_all_authenticated_users' => 'boolean',
        'allow_audio' => 'boolean',
        'allow_video' => 'boolean',
        'reminder_enabled' => 'boolean',
        'reminder_interval_minutes' => 'integer',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(GoshenExperienceQuestion::class, 'survey_id')->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(GoshenExperienceResponse::class, 'survey_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(GoshenExperienceReminder::class, 'survey_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('opens_at')->orWhere('opens_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            });
    }
}
