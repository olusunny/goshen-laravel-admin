<?php

namespace App\Jobs;

use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;
use App\Services\GoshenExperienceVideoStateMachine;
use App\Services\YouTube\YouTubeGateway;
use App\Services\YouTube\YouTubeGatewayException;
use App\Services\YouTube\YouTubeGatewayFailureKind;
use App\Services\YouTube\YouTubeQuotaWindowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UploadTriumphantExperienceVideoChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public readonly int $videoId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('triumphant-experience-video:'.$this->videoId))->expireAfter(55)];
    }

    public function handle(
        YouTubeGateway $gateway,
        GoshenExperienceVideoStateMachine $stateMachine,
        YouTubeQuotaWindowService $quota,
    ): void {
        $video = GoshenExperienceVideo::query()->with('youtubeConnection')->find($this->videoId);
        if (! $video || ! in_array($video->upload_status, [
            GoshenExperienceVideoUploadStatus::Queued,
            GoshenExperienceVideoUploadStatus::Uploading,
        ], true)) {
            return;
        }

        $connection = $video->youtubeConnection;
        if (! $connection || $connection->health === GoshenYouTubeConnection::HEALTH_REAUTH_REQUIRED || ! $connection->hasUsableGrant()) {
            $this->markReauthenticationRequired($video, $connection, $stateMachine);

            return;
        }

        if ($quota->circuitIsOpen($connection)) {
            $this->markQuotaDeferred($video, $connection, $stateMachine, $quota);

            return;
        }

        $connectionLock = Cache::lock($this->connectionLockKey($connection), 55);
        if (! $connectionLock->get()) {
            $this->deferUntilFifoHeadProgresses($video);

            return;
        }

        try {
            if (! $this->isFifoHead($video, $connection, $stateMachine)) {
                $this->deferUntilFifoHeadProgresses($video);

                return;
            }

            try {
                if ($video->upload_status === GoshenExperienceVideoUploadStatus::Queued) {
                    $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Uploading);
                }

                if (blank($video->resumable_session_url)) {
                    $video->resumable_session_url = $gateway->startResumableUpload($video, $connection);
                    $video->upload_attempts++;
                    $video->save();
                }

                $result = $gateway->uploadChunk($video->fresh(), $connection);

                if (! $result->complete()) {
                    $video->forceFill([
                        'uploaded_bytes' => $result->uploadedBytes,
                        'retry_after' => null,
                        'last_error_code' => null,
                        'last_error_message' => null,
                    ])->save();

                    self::dispatch($video->id)->onQueue(config('goshen_experience.youtube.upload_queue'));

                    return;
                }

                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::YoutubeProcessing);
                $video->forceFill([
                    'uploaded_bytes' => $result->uploadedBytes,
                    'youtube_video_id' => $result->youtubeVideoId,
                    'youtube_url' => 'https://www.youtube.com/watch?v='.$result->youtubeVideoId,
                    'youtube_privacy_status' => $connection->default_privacy,
                    'youtube_processing_status' => 'processing',
                    'youtube_uploaded_at' => now(),
                    'retry_after' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ])->save();

                CheckTriumphantExperienceVideoProcessing::dispatch($video->id)
                    ->onQueue(config('goshen_experience.youtube.upload_queue'))
                    ->delay(now()->addSeconds(30));

                ReleaseDeferredTriumphantExperienceUploads::dispatch($connection->id)
                    ->onQueue(config('goshen_experience.youtube.upload_queue'))
                    ->delay(now()->addSeconds((int) config('goshen_experience.youtube.fifo_pacing_seconds')));
            } catch (YouTubeGatewayException $exception) {
                $this->handleGatewayFailure($video->fresh(), $connection, $exception, $stateMachine, $quota);
            }
        } finally {
            $connectionLock->release();
        }
    }

    private function isFifoHead(
        GoshenExperienceVideo $video,
        GoshenYouTubeConnection $connection,
        GoshenExperienceVideoStateMachine $stateMachine,
    ): bool {
        $head = $this->activeFifoHead($connection);
        if (! $head) {
            return false;
        }

        if ($head->upload_status === GoshenExperienceVideoUploadStatus::Uploading && $this->isStale($head)) {
            $stateMachine->transition($head, GoshenExperienceVideoUploadStatus::Failed);
            $head->forceFill([
                'last_error_code' => 'youtube_upload_stale',
                'retry_after' => null,
            ])->save();
            $head = $this->activeFifoHead($connection);
        }

        return $head?->is($video) ?? false;
    }

    private function activeFifoHead(GoshenYouTubeConnection $connection): ?GoshenExperienceVideo
    {
        return GoshenExperienceVideo::query()
            ->where('youtube_connection_id', $connection->id)
            ->whereIn('upload_status', [
                GoshenExperienceVideoUploadStatus::Queued,
                GoshenExperienceVideoUploadStatus::Uploading,
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    private function isStale(GoshenExperienceVideo $video): bool
    {
        $staleAfterSeconds = max(120, (int) config('goshen_experience.youtube.upload_slice_seconds') * 3);

        return $video->updated_at?->lte(now()->subSeconds($staleAfterSeconds)) ?? false;
    }

    private function deferUntilFifoHeadProgresses(GoshenExperienceVideo $video): void
    {
        $latest = $video->fresh();
        if (! $latest || ! in_array($latest->upload_status, [
            GoshenExperienceVideoUploadStatus::Queued,
            GoshenExperienceVideoUploadStatus::Uploading,
        ], true)) {
            return;
        }

        self::dispatch($latest->id)
            ->onQueue(config('goshen_experience.youtube.upload_queue'))
            ->delay(now()->addSeconds((int) config('goshen_experience.youtube.fifo_pacing_seconds')));
    }

    private function connectionLockKey(GoshenYouTubeConnection $connection): string
    {
        return 'triumphant-experience-youtube-connection:'.$connection->id;
    }

    private function handleGatewayFailure(
        GoshenExperienceVideo $video,
        GoshenYouTubeConnection $connection,
        YouTubeGatewayException $exception,
        GoshenExperienceVideoStateMachine $stateMachine,
        YouTubeQuotaWindowService $quota,
    ): void {
        if ($exception->kind === YouTubeGatewayFailureKind::DailyQuota) {
            $this->markQuotaDeferred($video, $connection, $stateMachine, $quota);

            return;
        }

        if ($exception->kind === YouTubeGatewayFailureKind::ReauthenticationRequired) {
            $this->markReauthenticationRequired($video, $connection, $stateMachine);

            return;
        }

        if (in_array($exception->kind, [YouTubeGatewayFailureKind::Transient, YouTubeGatewayFailureKind::RateLimited], true)) {
            $retryAt = now()->addSeconds($exception->retryAfterSeconds ?? 30);
            $video->forceFill([
                'retry_after' => $retryAt,
                'last_error_code' => $exception->safeCode,
            ])->save();

            self::dispatch($video->id)
                ->onQueue(config('goshen_experience.youtube.upload_queue'))
                ->delay($retryAt);

            return;
        }

        if ($video->upload_status !== GoshenExperienceVideoUploadStatus::Failed) {
            $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Failed);
        }

        $video->forceFill([
            'last_error_code' => $exception->safeCode,
            'retry_after' => null,
        ])->save();
    }

    private function markQuotaDeferred(
        GoshenExperienceVideo $video,
        GoshenYouTubeConnection $connection,
        GoshenExperienceVideoStateMachine $stateMachine,
        YouTubeQuotaWindowService $quota,
    ): void {
        DB::transaction(function () use ($video, $connection, $quota, $stateMachine): void {
            $lockedConnection = GoshenYouTubeConnection::query()->lockForUpdate()->findOrFail($connection->id);
            $lockedVideo = GoshenExperienceVideo::query()->lockForUpdate()->findOrFail($video->id);
            $resumeAt = $quota->deferConnection($lockedConnection);

            if ($lockedVideo->upload_status !== GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota) {
                $stateMachine->transition($lockedVideo, GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota);
            }

            $lockedVideo->forceFill([
                'quota_deferred_at' => now(),
                'quota_resume_at' => $resumeAt,
                'retry_after' => $resumeAt,
                'last_error_code' => 'youtube_daily_quota',
            ])->save();
        });
    }

    private function markReauthenticationRequired(
        GoshenExperienceVideo $video,
        ?GoshenYouTubeConnection $connection,
        GoshenExperienceVideoStateMachine $stateMachine,
    ): void {
        if ($video->upload_status !== GoshenExperienceVideoUploadStatus::ReauthRequired) {
            $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::ReauthRequired);
        }

        $video->forceFill([
            'last_error_code' => 'youtube_connection_reauth_required',
            'retry_after' => null,
        ])->save();

        $connection?->forceFill([
            'health' => GoshenYouTubeConnection::HEALTH_REAUTH_REQUIRED,
            'last_error_code' => 'youtube_connection_reauth_required',
        ])->save();
    }
}
