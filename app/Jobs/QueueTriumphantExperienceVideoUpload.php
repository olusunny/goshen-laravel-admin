<?php

namespace App\Jobs;

use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;
use App\Services\GoshenExperienceVideoStateMachine;
use App\Services\YouTube\YouTubeQuotaWindowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class QueueTriumphantExperienceVideoUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $videoId) {}

    public function handle(GoshenExperienceVideoStateMachine $stateMachine, YouTubeQuotaWindowService $quota): void
    {
        $video = GoshenExperienceVideo::query()->with('youtubeConnection')->find($this->videoId);
        if (! $video || $video->upload_status === GoshenExperienceVideoUploadStatus::Cancelled) {
            return;
        }

        $connection = $video->youtubeConnection ?: $this->defaultConnection();
        if (! $connection) {
            if ($video->upload_status === GoshenExperienceVideoUploadStatus::Received) {
                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Queued);
            }

            if ($video->upload_status === GoshenExperienceVideoUploadStatus::Queued) {
                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::ReauthRequired);
                $video->forceFill(['last_error_code' => 'youtube_connection_missing'])->save();
            }

            return;
        }

        if ($video->youtube_connection_id !== $connection->id) {
            $video->youtube_connection_id = $connection->id;
        }

        if ($quota->circuitIsOpen($connection)) {
            if ($video->upload_status === GoshenExperienceVideoUploadStatus::Received) {
                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota);
            } elseif ($video->upload_status === GoshenExperienceVideoUploadStatus::Queued) {
                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota);
            }

            $video->forceFill([
                'quota_deferred_at' => now(),
                'quota_resume_at' => $connection->quota_resume_at,
                'retry_after' => $connection->quota_resume_at,
                'last_error_code' => 'youtube_daily_quota',
            ])->save();

            return;
        }

        if ($connection->health === GoshenYouTubeConnection::HEALTH_REAUTH_REQUIRED || ! $connection->hasUsableGrant()) {
            if ($video->upload_status === GoshenExperienceVideoUploadStatus::Received) {
                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Queued);
            }

            if ($video->upload_status === GoshenExperienceVideoUploadStatus::Queued) {
                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::ReauthRequired);
                $video->forceFill(['last_error_code' => 'youtube_connection_reauth_required'])->save();
            }

            return;
        }

        if ($video->upload_status === GoshenExperienceVideoUploadStatus::Received) {
            $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Queued);
            $video->save();
        }

        if ($video->upload_status !== GoshenExperienceVideoUploadStatus::Queued) {
            return;
        }

        UploadTriumphantExperienceVideoChunk::dispatch($video->id)
            ->onQueue(config('goshen_experience.youtube.upload_queue'));
    }

    private function defaultConnection(): ?GoshenYouTubeConnection
    {
        return GoshenYouTubeConnection::query()
            ->whereIn('health', [
                GoshenYouTubeConnection::HEALTH_HEALTHY,
                GoshenYouTubeConnection::HEALTH_QUOTA_BLOCKED,
            ])
            ->orderBy('id')
            ->first();
    }
}
