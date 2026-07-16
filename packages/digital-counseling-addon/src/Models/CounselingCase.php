<?php

namespace ChurchTools\DigitalCounseling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CounselingCase extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_TRIAGE = 'triage';
    public const STATUS_AWAITING_ASSIGNMENT = 'awaiting_assignment';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_AWAITING_REQUESTER = 'awaiting_requester';
    public const STATUS_AWAITING_COUNSELOR = 'awaiting_counselor';
    public const STATUS_FOLLOW_UP = 'follow_up';
    public const STATUS_CLOSED = 'closed';

    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(config('counseling.models.requester'), 'requester_mobile_user_id');
    }

    public function assignedProviderProfile(): BelongsTo
    {
        return $this->belongsTo(CounselingProviderProfile::class, 'assigned_provider_profile_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CounselingMessage::class, 'case_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(CounselingMessage::class, 'case_id')->latestOfMany();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CounselingAssignment::class, 'case_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CounselingCaseNote::class, 'case_id');
    }

    public function safeguardingEvents(): HasMany
    {
        return $this->hasMany(CounselingSafeguardingEvent::class, 'case_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CounselingCaseEvent::class, 'case_id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED || $this->closed_at !== null;
    }
}
