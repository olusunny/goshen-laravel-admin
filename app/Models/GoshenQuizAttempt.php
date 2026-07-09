<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\Ticket;

class GoshenQuizAttempt extends Model
{
    public const STATUS_STARTED = 'started';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_TIMED_OUT = 'timed_out';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'timed_out_at' => 'datetime',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'correct_count' => 'integer',
        'answered_count' => 'integer',
        'total_questions' => 'integer',
        'elapsed_seconds' => 'integer',
        'answers' => 'array',
        'metadata' => 'array',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(GoshenQuiz::class, 'quiz_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
