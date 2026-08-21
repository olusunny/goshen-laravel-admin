<?php

namespace App\Models;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use Database\Factories\GoshenExperienceVideoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\Ticket;

class GoshenExperienceVideo extends Model
{
    /** @use HasFactory<GoshenExperienceVideoFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'event_id', 'mobile_user_id', 'booking_id', 'ticket_id', 'goshen_experience_survey_id', 'client_upload_id',
        'caption', 'display_name', 'is_anonymous', 'consent_version', 'consented_at',
        'local_disk', 'local_path', 'original_filename', 'mime_type', 'file_size_bytes', 'duration_seconds', 'width', 'height', 'sha256', 'active_submission_key',
        'youtube_connection_id', 'youtube_video_id', 'youtube_url', 'youtube_thumbnail_url', 'youtube_privacy_status', 'youtube_processing_status',
        'resumable_session_url', 'uploaded_bytes', 'upload_attempts', 'youtube_uploaded_at', 'youtube_processed_at',
        'upload_status', 'moderation_status', 'last_error_code', 'last_error_message', 'retry_after', 'quota_deferred_at', 'quota_resume_at',
        'local_deleted_at', 'moderated_by_id', 'approved_at', 'rejected_at', 'removed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'consented_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'duration_seconds' => 'decimal:3',
            'width' => 'integer',
            'height' => 'integer',
            'resumable_session_url' => 'encrypted',
            'uploaded_bytes' => 'integer',
            'upload_attempts' => 'integer',
            'youtube_uploaded_at' => 'datetime',
            'youtube_processed_at' => 'datetime',
            'upload_status' => GoshenExperienceVideoUploadStatus::class,
            'moderation_status' => GoshenExperienceVideoModerationStatus::class,
            'retry_after' => 'datetime',
            'quota_deferred_at' => 'datetime',
            'quota_resume_at' => 'datetime',
            'local_deleted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GoshenExperienceVideo $video): void {
            $video->uuid ??= (string) Str::uuid();
            $video->upload_status ??= GoshenExperienceVideoUploadStatus::Received;
            $video->moderation_status ??= GoshenExperienceVideoModerationStatus::Pending;
            $video->active_submission_key ??= 'active';
        });
    }

    protected static function newFactory(): GoshenExperienceVideoFactory
    {
        return GoshenExperienceVideoFactory::new();
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

    public function survey(): BelongsTo
    {
        return $this->belongsTo(GoshenExperienceSurvey::class, 'goshen_experience_survey_id');
    }

    public function youtubeConnection(): BelongsTo
    {
        return $this->belongsTo(GoshenYouTubeConnection::class, 'youtube_connection_id');
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_id');
    }

    public function scopeFeedVisible(Builder $query): Builder
    {
        return $query
            ->where('upload_status', GoshenExperienceVideoUploadStatus::ReadyForReview)
            ->where('moderation_status', GoshenExperienceVideoModerationStatus::Approved)
            ->whereNotNull('youtube_video_id')
            ->whereIn('youtube_privacy_status', ['unlisted', 'public']);
    }

    public function isFeedVisible(): bool
    {
        return $this->upload_status === GoshenExperienceVideoUploadStatus::ReadyForReview
            && $this->moderation_status === GoshenExperienceVideoModerationStatus::Approved
            && filled($this->youtube_video_id)
            && in_array($this->youtube_privacy_status, ['unlisted', 'public'], true);
    }

    public function displayLabel(): string
    {
        return $this->is_anonymous ? 'Anonymous' : ($this->display_name ?: 'Triumphant attendee');
    }
}
