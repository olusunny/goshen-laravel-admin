<?php

namespace ChurchTools\CloudBackup\Commands;

use ChurchTools\CloudBackup\Jobs\RunBackupJob;
use ChurchTools\CloudBackup\Models\CloudBackupSchedule;
use Illuminate\Console\Command;

class RunDueBackupsCommand extends Command
{
    protected $signature = 'cloud-backup:run-due {--sync : Run due backups in the current process instead of dispatching queue jobs}';

    protected $description = 'Dispatch due cloud backup schedules.';

    public function handle(): int
    {
        $schedules = CloudBackupSchedule::query()
            ->where('enabled', true)
            ->where(function ($query): void {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->get();

        foreach ($schedules as $schedule) {
            if ($this->option('sync')) {
                app(\ChurchTools\CloudBackup\Services\BackupManager::class)->runSchedule($schedule);
            } else {
                RunBackupJob::dispatch($schedule->id);
            }
        }

        $this->info("Queued {$schedules->count()} due backup schedule(s).");

        return self::SUCCESS;
    }
}
