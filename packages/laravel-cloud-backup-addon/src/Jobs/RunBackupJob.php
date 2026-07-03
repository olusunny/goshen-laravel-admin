<?php

namespace ChurchTools\CloudBackup\Jobs;

use ChurchTools\CloudBackup\Models\CloudBackupSchedule;
use ChurchTools\CloudBackup\Services\BackupManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RunBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(public readonly int $scheduleId)
    {
        $this->onQueue(config('cloud-backup.queue', 'default'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('cloud-backup-schedule-'.$this->scheduleId))->expireAfter(7200),
        ];
    }

    public function handle(BackupManager $backupManager): void
    {
        $schedule = CloudBackupSchedule::query()
            ->with('connection')
            ->whereKey($this->scheduleId)
            ->where('enabled', true)
            ->firstOrFail();

        $backupManager->runSchedule($schedule);
    }
}
