<?php

namespace App\Console\Commands;

use App\Services\ScheduledInboxMessageService;
use Illuminate\Console\Command;

class SendScheduledInboxMessages extends Command
{
    protected $signature = 'inbox:send-scheduled {--limit=50 : Maximum scheduled messages to process}';

    protected $description = 'Dispatch due scheduled inbox, push, and email messages.';

    public function handle(ScheduledInboxMessageService $messages): int
    {
        $processed = $messages->processDue((int) $this->option('limit'));

        $this->info("Processed {$processed} scheduled inbox message(s).");

        return self::SUCCESS;
    }
}
