<?php

namespace ChurchTools\CloudBackup\Jobs;

use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Models\CloudBackupRun;
use ChurchTools\CloudBackup\Services\BackupManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RunOnDemandBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    /**
     * @param array{
     *     include_files: bool,
     *     include_database: bool,
     *     source_path?: ?string,
     *     database_connection?: ?string,
     *     exclude_paths?: array<int, string>,
     *     retention_count: int
     * } $options
     */
    public function __construct(
        public readonly int $connectionId,
        public readonly int $runId,
        public readonly array $options
    ) {
        $this->onQueue(config('cloud-backup.queue', 'default'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('cloud-backup-connection-'.$this->connectionId))->expireAfter(7200),
        ];
    }

    public function handle(BackupManager $backupManager): void
    {
        $connection = CloudBackupConnection::query()->whereKey($this->connectionId)->firstOrFail();
        $run = CloudBackupRun::query()
            ->whereKey($this->runId)
            ->where('connection_id', $connection->id)
            ->firstOrFail();

        $backupManager->runOnDemand($connection, $this->options, $run);
    }
}
