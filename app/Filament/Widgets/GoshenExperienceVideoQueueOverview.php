<?php

namespace App\Filament\Widgets;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Filament\Resources\GoshenExperienceVideoResource;
use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class GoshenExperienceVideoQueueOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return GoshenExperienceVideoResource::canViewAny();
    }

    protected function getStats(): array
    {
        $readyForReview = GoshenExperienceVideo::query()
            ->where('upload_status', GoshenExperienceVideoUploadStatus::ReadyForReview)
            ->where('moderation_status', GoshenExperienceVideoModerationStatus::Pending)
            ->count();
        $inFlight = GoshenExperienceVideo::query()
            ->whereIn('upload_status', [GoshenExperienceVideoUploadStatus::Uploading, GoshenExperienceVideoUploadStatus::YoutubeProcessing])
            ->count();
        $quotaWaiting = GoshenExperienceVideo::query()->where('upload_status', GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota)->count();
        $failed = GoshenExperienceVideo::query()->whereIn('upload_status', [GoshenExperienceVideoUploadStatus::Failed, GoshenExperienceVideoUploadStatus::ReauthRequired])->count();
        $published = GoshenExperienceVideo::query()->where('moderation_status', GoshenExperienceVideoModerationStatus::Approved)->count();
        $cleanupAttention = GoshenExperienceVideo::query()
            ->where('upload_status', GoshenExperienceVideoUploadStatus::ReadyForReview)
            ->whereNotNull('youtube_video_id')
            ->whereNull('local_deleted_at')
            ->count();
        $nextQuotaRetry = GoshenYouTubeConnection::query()->whereNotNull('quota_resume_at')->min('quota_resume_at');

        return [
            Stat::make('Awaiting review', number_format($readyForReview))->description('Moderation decision required')->color('warning'),
            Stat::make('Uploading or processing', number_format($inFlight))->description('Private source remains retained')->color('info'),
            Stat::make('Waiting for daily quota', number_format($quotaWaiting))->description($nextQuotaRetry ? 'Next retry: '.Carbon::parse($nextQuotaRetry)->timezone('Africa/Lagos')->format('M j, g:i A T') : 'No scheduled retry')->color('warning'),
            Stat::make('Failed or re-auth needed', number_format($failed))->description('Safe retry or reconnection needed')->color('danger'),
            Stat::make('Published', number_format($published))->description('Approved mobile-feed items')->color('success'),
            Stat::make('Cleanup attention', number_format($cleanupAttention))->description('Verified YouTube asset, local cleanup pending')->color('gray'),
        ];
    }
}
