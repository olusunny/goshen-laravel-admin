<?php

namespace App\Services;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Models\GoshenExperienceVideo;
use DomainException;

class GoshenExperienceVideoStateMachine
{
    /** @var array<string, list<GoshenExperienceVideoUploadStatus>> */
    private const UPLOAD_TRANSITIONS = [
        'received' => [GoshenExperienceVideoUploadStatus::Queued, GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, GoshenExperienceVideoUploadStatus::Cancelled],
        'queued' => [GoshenExperienceVideoUploadStatus::Uploading, GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, GoshenExperienceVideoUploadStatus::Failed, GoshenExperienceVideoUploadStatus::ReauthRequired, GoshenExperienceVideoUploadStatus::Cancelled],
        'uploading' => [GoshenExperienceVideoUploadStatus::YoutubeProcessing, GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, GoshenExperienceVideoUploadStatus::Failed, GoshenExperienceVideoUploadStatus::ReauthRequired],
        'awaiting_youtube_quota' => [GoshenExperienceVideoUploadStatus::Queued, GoshenExperienceVideoUploadStatus::YoutubeProcessing, GoshenExperienceVideoUploadStatus::Cancelled],
        'youtube_processing' => [GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, GoshenExperienceVideoUploadStatus::ReadyForReview, GoshenExperienceVideoUploadStatus::Failed, GoshenExperienceVideoUploadStatus::ReauthRequired],
        'failed' => [GoshenExperienceVideoUploadStatus::Queued, GoshenExperienceVideoUploadStatus::Cancelled],
        'reauth_required' => [GoshenExperienceVideoUploadStatus::Queued, GoshenExperienceVideoUploadStatus::Cancelled],
        'ready_for_review' => [GoshenExperienceVideoUploadStatus::Cancelled],
        'cancelled' => [],
    ];

    /** @var array<string, list<GoshenExperienceVideoModerationStatus>> */
    private const MODERATION_TRANSITIONS = [
        'pending' => [GoshenExperienceVideoModerationStatus::Approved, GoshenExperienceVideoModerationStatus::Rejected],
        'approved' => [GoshenExperienceVideoModerationStatus::Removed],
        'rejected' => [],
        'removed' => [GoshenExperienceVideoModerationStatus::Approved],
    ];

    public function transition(GoshenExperienceVideo $video, GoshenExperienceVideoUploadStatus $to): void
    {
        $from = $video->upload_status instanceof GoshenExperienceVideoUploadStatus
            ? $video->upload_status
            : GoshenExperienceVideoUploadStatus::from((string) $video->upload_status);

        if (! in_array($to, self::UPLOAD_TRANSITIONS[$from->value], true)) {
            throw new DomainException("Cannot transition a Triumphant Experience video from {$from->value} to {$to->value}.");
        }

        $video->upload_status = $to;
        if ($to === GoshenExperienceVideoUploadStatus::Cancelled) {
            $video->active_submission_key = null;
        }
    }

    public function transitionModeration(GoshenExperienceVideo $video, GoshenExperienceVideoModerationStatus $to): void
    {
        $from = $video->moderation_status instanceof GoshenExperienceVideoModerationStatus
            ? $video->moderation_status
            : GoshenExperienceVideoModerationStatus::from((string) $video->moderation_status);

        if (! in_array($to, self::MODERATION_TRANSITIONS[$from->value], true)) {
            throw new DomainException("Cannot transition Triumphant Experience moderation from {$from->value} to {$to->value}.");
        }

        $video->moderation_status = $to;
    }

    public function canDeleteLocalSource(GoshenExperienceVideo $video): bool
    {
        $uploadStatus = $video->upload_status instanceof GoshenExperienceVideoUploadStatus
            ? $video->upload_status
            : GoshenExperienceVideoUploadStatus::tryFrom((string) $video->upload_status);
        $sourcePrefix = $this->normalizeRelativePath((string) config('goshen_experience.source_path', 'goshen/experience/shorts/source'));
        $localPath = $this->normalizeRelativePath((string) $video->local_path);

        return $uploadStatus === GoshenExperienceVideoUploadStatus::ReadyForReview
            && filled($video->youtube_video_id)
            && $video->youtube_processed_at !== null
            && $video->local_deleted_at === null
            && $video->local_disk === config('goshen_experience.source_disk', 'local')
            && $sourcePrefix !== null
            && $localPath !== null
            && str_starts_with($localPath, $sourcePrefix.'/');
    }

    private function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        $segments = explode('/', $path);
        if (collect($segments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..')) {
            return null;
        }

        return implode('/', $segments);
    }
}
