<?php

namespace App\Console\Commands;

use App\Jobs\DeleteExpiredCommunityPrayerRequests;
use Illuminate\Console\Command;

class PurgeExpiredCommunityPrayerRequests extends Command
{
    protected $signature = 'prayer-community:purge-expired {--sync : Run immediately instead of dispatching a queue job}';

    protected $description = 'Delete expired interactive prayer requests and their audio, comments, flags, and suggestions.';

    public function handle(): int
    {
        if ($this->option('sync')) {
            app(DeleteExpiredCommunityPrayerRequests::class)->handle();
            $this->info('Expired interactive prayer requests purged.');

            return self::SUCCESS;
        }

        DeleteExpiredCommunityPrayerRequests::dispatch();
        $this->info('Expired interactive prayer request purge job dispatched.');

        return self::SUCCESS;
    }
}
