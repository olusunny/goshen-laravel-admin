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

class CheckTriumphantExperienceVideoProcessing implements ShouldQueue
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
        if (! $video || $video->upload_status !== GoshenExperienceVideoUploadStatus::YoutubeProcessing) {
            return;
        }

        $connection = $video->youtubeConnection;
        if (! $connection || $connection->health === GoshenYouTubeConnection::HEALTH_REAUTH_REQUIRED || ! $connection->hasUsableGrant()) {
            $this->markReauthenticationRequired($video, $connection, $stateMachine);

            return;
        }

        if ($quota->circuitIsOpen($connection)) {
            self::dispatch($video->id)
                ->onQueue(config('goshen_experience.youtube.upload_queue'))
                ->delay($connection->quota_resume_at);

            return;
        }

        try {
            $result = $gateway->processingStatus($video, $connection);
        } catch (YouTubeGatewayException $exception) {
            if ($exception->kind === YouTubeGatewayFailureKind::DailyQuota) {
                $resumeAt = $quota->deferConnection($connection);
                $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota);
                $video->forceFill([
                    'quota_deferred_at' => now(),
                    'quota_resume_at' => $resumeAt,
                    'retry_after' => $resumeAt,
                    'last_error_code' => 'youtube_daily_quota',
                ])->save();

                return;
            }

            if ($exception->kind === YouTubeGatewayFailureKind::ReauthenticationRequired) {
                $this->markReauthenticationRequired($video, $connection, $stateMachine);

                return;
            }

            if (in_array($exception->kind, [YouTubeGatewayFailureKind::Transient, YouTubeGatewayFailureKind::RateLimited], true)) {
                $retryAt = now()->addSeconds($exception->retryAfterSeconds ?? 30);
                $video->forceFill(['retry_after' => $retryAt, 'last_error_code' => $exception->safeCode])->save();
                self::dispatch($video->id)->onQueue(config('goshen_experience.youtube.upload_queue'))->delay($retryAt);

                return;
            }

            $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Failed);
            $video->forceFill(['last_error_code' => $exception->safeCode, 'retry_after' => null])->save();

            return;
        }

        if ($result['state'] === 'succeeded') {
            $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::ReadyForReview);
            $video->forceFill([
                'youtube_processing_status' => 'succeeded',
                'youtube_thumbnail_url' => $result['thumbnail_url'],
                'youtube_processed_at' => now(),
                'retry_after' => null,
                'last_error_code' => null,
            ])->save();

            DeleteTriumphantExperienceVideoSource::dispatch($video->id)
                ->onQueue(config('goshen_experience.youtube.upload_queue'));

            return;
        }

        if ($result['state'] === 'failed') {
            $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Failed);
            $video->forceFill([
                'youtube_processing_status' => 'failed',
                'last_error_code' => 'youtube_processing_failed',
            ])->save();

            return;
        }

        $video->forceFill(['youtube_processing_status' => 'processing'])->save();
        self::dispatch($video->id)
            ->onQueue(config('goshen_experience.youtube.upload_queue'))
            ->delay(now()->addSeconds(30));
    }

    private function markReauthenticationRequired(
        GoshenExperienceVideo $video,
        ?GoshenYouTubeConnection $connection,
        GoshenExperienceVideoStateMachine $stateMachine,
    ): void {
        $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::ReauthRequired);
        $video->forceFill(['last_error_code' => 'youtube_connection_reauth_required', 'retry_after' => null])->save();
        $connection?->forceFill([
            'health' => GoshenYouTubeConnection::HEALTH_REAUTH_REQUIRED,
            'last_error_code' => 'youtube_connection_reauth_required',
        ])->save();
    }
}
