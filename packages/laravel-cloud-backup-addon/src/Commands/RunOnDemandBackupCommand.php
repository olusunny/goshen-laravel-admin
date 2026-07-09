<?php

namespace ChurchTools\CloudBackup\Commands;

use ChurchTools\CloudBackup\Models\CloudBackupRun;
use ChurchTools\CloudBackup\Services\BackupManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Throwable;

class RunOnDemandBackupCommand extends Command
{
    protected $signature = 'cloud-backup:run-on-demand
        {run : Cloud backup run ID}
        {--options= : Base64 encoded JSON backup options}';

    protected $description = 'Run a single on-demand cloud backup immediately and update its progress.';

    public function handle(BackupManager $backupManager): int
    {
        $run = CloudBackupRun::query()
            ->with('connection')
            ->whereKey((int) $this->argument('run'))
            ->firstOrFail();

        if (! $run->connection) {
            $run->forceFill([
                'status' => CloudBackupRun::STATUS_FAILED,
                'progress_percent' => 100,
                'current_step' => 'Backup connection is no longer available',
                'finished_at' => now(),
                'error_summary' => 'Backup connection is no longer available.',
            ])->save();

            return self::FAILURE;
        }

        try {
            $options = $this->decodeOptions((string) $this->option('options'));
        } catch (InvalidArgumentException $exception) {
            $run->forceFill([
                'status' => CloudBackupRun::STATUS_FAILED,
                'progress_percent' => 100,
                'current_step' => 'Backup options could not be read',
                'finished_at' => now(),
                'error_summary' => $exception->getMessage(),
            ])->save();

            return self::FAILURE;
        }

        try {
            $backupManager->runOnDemand($run->connection, $options, $run);
        } catch (Throwable $throwable) {
            $run->refresh();

            if (! in_array($run->status, [CloudBackupRun::STATUS_FAILED, CloudBackupRun::STATUS_SUCCEEDED], true)) {
                $run->forceFill([
                    'status' => CloudBackupRun::STATUS_FAILED,
                    'progress_percent' => 100,
                    'current_step' => 'Backup failed',
                    'finished_at' => now(),
                    'error_summary' => $this->safeError($throwable),
                ])->save();
            }

            $this->error($this->safeError($throwable));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     include_files: bool,
     *     include_database: bool,
     *     source_path?: ?string,
     *     database_connection?: ?string,
     *     exclude_paths?: array<int, string>,
     *     retention_count: int
     * }
     */
    private function decodeOptions(string $encoded): array
    {
        $json = base64_decode($encoded, true);

        if ($json === false || $json === '') {
            throw new InvalidArgumentException('Backup options were not provided correctly.');
        }

        try {
            $options = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Backup options contain invalid JSON.');
        }

        if (! is_array($options)) {
            throw new InvalidArgumentException('Backup options must be an object.');
        }

        return [
            'include_files' => (bool) ($options['include_files'] ?? false),
            'include_database' => (bool) ($options['include_database'] ?? false),
            'source_path' => $options['source_path'] ?? null,
            'database_connection' => $options['database_connection'] ?? null,
            'exclude_paths' => is_array($options['exclude_paths'] ?? null) ? $options['exclude_paths'] : [],
            'retention_count' => max(1, (int) ($options['retention_count'] ?? 7)),
        ];
    }

    private function safeError(Throwable $throwable): string
    {
        $message = trim($throwable->getMessage());

        if ($message === '') {
            return 'Backup worker stopped with an unknown error.';
        }

        return str($message)->replaceMatches('/\s+/', ' ')->limit(500, '')->toString();
    }
}
