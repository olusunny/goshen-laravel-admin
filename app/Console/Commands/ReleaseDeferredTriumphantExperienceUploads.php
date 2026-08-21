<?php

namespace App\Console\Commands;

use App\Jobs\ReleaseDeferredTriumphantExperienceUploads as ReleaseDeferredJob;
use App\Models\GoshenYouTubeConnection;
use Illuminate\Console\Command;

class ReleaseDeferredTriumphantExperienceUploads extends Command
{
    protected $signature = 'goshen:release-deferred-triumphant-experience-uploads {--limit=10}';

    protected $description = 'Resume one FIFO Triumphant Experience YouTube upload for each due quota window.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $connectionIds = GoshenYouTubeConnection::query()
            ->whereNotNull('quota_resume_at')
            ->where('quota_resume_at', '<=', now())
            ->orderBy('quota_resume_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($connectionIds as $connectionId) {
            ReleaseDeferredJob::dispatch((int) $connectionId, fromScheduledProbe: true)
                ->onQueue(config('goshen_experience.youtube.upload_queue'));
        }

        $this->info("Released {$connectionIds->count()} due Triumphant Experience quota probe(s).");

        return self::SUCCESS;
    }
}
