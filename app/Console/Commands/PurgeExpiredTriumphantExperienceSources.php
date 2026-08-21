<?php

namespace App\Console\Commands;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Models\GoshenExperienceVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeExpiredTriumphantExperienceSources extends Command
{
    protected $signature = 'goshen:purge-expired-triumphant-experience-sources
        {--dry-run : Report eligible private sources without deleting them}
        {--force : Delete only after a reviewed dry run}
        {--limit=100 : Maximum records to inspect}';

    protected $description = 'Purge only retention-expired failed or rejected Triumphant Experience private sources.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('force');
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subDays(max(1, (int) config('goshen_experience.failed_source_retention_days')));
        $candidates = GoshenExperienceVideo::query()
            ->whereNull('local_deleted_at')
            ->whereNotNull('local_path')
            ->where('created_at', '<=', $cutoff)
            ->whereIn('upload_status', [
                GoshenExperienceVideoUploadStatus::Failed,
                GoshenExperienceVideoUploadStatus::ReadyForReview,
            ])
            ->where('upload_status', '!=', GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->filter(fn (GoshenExperienceVideo $video): bool => $this->canPurge($video));

        if ($candidates->isEmpty()) {
            $this->info('No retention-expired Triumphant Experience sources are eligible for cleanup.');

            return self::SUCCESS;
        }

        $this->table(['Video ID', 'Upload state', 'Moderation state', 'Eligibility'], $candidates->map(fn (GoshenExperienceVideo $video): array => [
            $video->id,
            $video->upload_status->value,
            $video->moderation_status->value,
            'Eligible private source',
        ])->all());

        if ($dryRun) {
            $this->warn('Dry run only. Re-run with --force only after an authorised audit review.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($candidates as $video) {
            try {
                if (! Storage::disk($video->local_disk)->delete($video->local_path)) {
                    $this->warn("Video {$video->id}: source cleanup did not complete.");

                    continue;
                }

                $video->forceFill([
                    'local_deleted_at' => now(),
                    'local_path' => null,
                ])->save();
                $deleted++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("Video {$video->id}: source cleanup did not complete.");
            }
        }

        $this->info("Deleted {$deleted} retention-expired private source(s).");

        return self::SUCCESS;
    }

    private function canPurge(GoshenExperienceVideo $video): bool
    {
        $prefix = trim((string) config('goshen_experience.source_path'), '/').'/';
        if ($video->local_disk !== config('goshen_experience.source_disk') || ! str_starts_with((string) $video->local_path, $prefix)) {
            return false;
        }

        if (filled($video->resumable_session_url) || $video->retry_after?->isFuture()) {
            return false;
        }

        if ($video->upload_status === GoshenExperienceVideoUploadStatus::Failed) {
            return true;
        }

        return $video->upload_status === GoshenExperienceVideoUploadStatus::ReadyForReview
            && $video->moderation_status === GoshenExperienceVideoModerationStatus::Rejected
            && filled($video->youtube_video_id)
            && $video->youtube_processed_at !== null;
    }
}
