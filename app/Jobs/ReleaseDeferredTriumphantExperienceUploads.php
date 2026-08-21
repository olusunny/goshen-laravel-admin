<?php

namespace App\Jobs;

use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;
use App\Services\GoshenExperienceVideoStateMachine;
use App\Services\YouTube\YouTubeQuotaWindowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReleaseDeferredTriumphantExperienceUploads implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $connectionId, public readonly bool $fromScheduledProbe = false) {}

    public function handle(GoshenExperienceVideoStateMachine $stateMachine, YouTubeQuotaWindowService $quota): void
    {
        $lock = Cache::lock('triumphant-experience-youtube-connection:'.$this->connectionId, 60);
        if (! $lock->get()) {
            return;
        }

        try {
            $release = DB::transaction(function () use ($stateMachine, $quota): ?array {
                $connection = GoshenYouTubeConnection::query()->lockForUpdate()->find($this->connectionId);
                if (! $connection) {
                    return null;
                }

                if ($quota->circuitIsOpen($connection)) {
                    return null;
                }

                if ($this->fromScheduledProbe && $connection->quota_resume_at !== null) {
                    $quota->markResumed($connection);
                }

                if ($connection->health !== GoshenYouTubeConnection::HEALTH_HEALTHY || ! $connection->hasUsableGrant()) {
                    return null;
                }

                $video = GoshenExperienceVideo::query()
                    ->where('youtube_connection_id', $connection->id)
                    ->where('upload_status', GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $video) {
                    return null;
                }

                $resumeProcessing = filled($video->youtube_video_id) && $video->youtube_uploaded_at !== null;
                $stateMachine->transition(
                    $video,
                    $resumeProcessing
                        ? GoshenExperienceVideoUploadStatus::YoutubeProcessing
                        : GoshenExperienceVideoUploadStatus::Queued,
                );
                $video->forceFill([
                    'retry_after' => null,
                    'quota_resume_at' => null,
                    'last_error_code' => null,
                ])->save();

                return ['video_id' => $video->id, 'resume_processing' => $resumeProcessing];
            });

            if ($release !== null) {
                $job = $release['resume_processing']
                    ? new CheckTriumphantExperienceVideoProcessing($release['video_id'])
                    : new QueueTriumphantExperienceVideoUpload($release['video_id']);

                dispatch($job)->onQueue(config('goshen_experience.youtube.upload_queue'));
            }
        } finally {
            optional($lock)->release();
        }
    }
}
