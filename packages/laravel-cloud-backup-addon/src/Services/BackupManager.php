<?php

namespace ChurchTools\CloudBackup\Services;

use ChurchTools\CloudBackup\Models\CloudBackupArtifact;
use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Models\CloudBackupRun;
use ChurchTools\CloudBackup\Models\CloudBackupSchedule;
use ChurchTools\CloudBackup\Services\Cloud\CloudProviderFactory;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BackupManager
{
    public function __construct(
        private readonly FileArchiveService $fileArchive,
        private readonly DatabaseDumpService $databaseDump,
        private readonly CloudProviderFactory $providerFactory,
        private readonly RetentionManager $retentionManager
    ) {
    }

    public function runSchedule(CloudBackupSchedule $schedule): CloudBackupRun
    {
        $connection = $schedule->connection()->firstOrFail();

        return $this->runBackup($connection, $schedule);
    }

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
    public function runOnDemand(CloudBackupConnection $connection, array $options, ?CloudBackupRun $run = null): CloudBackupRun
    {
        $settings = new CloudBackupSchedule([
            'include_files' => (bool) $options['include_files'],
            'include_database' => (bool) $options['include_database'],
            'source_path' => $options['source_path'] ?? null,
            'database_connection' => $options['database_connection'] ?? null,
            'exclude_paths' => $options['exclude_paths'] ?? [],
            'retention_count' => (int) $options['retention_count'],
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        return $this->runBackup($connection, $settings, $run);
    }

    private function runBackup(CloudBackupConnection $connection, CloudBackupSchedule $settings, ?CloudBackupRun $existingRun = null): CloudBackupRun
    {
        $run = $existingRun ?: CloudBackupRun::create([
            'connection_id' => $connection->id,
            'schedule_id' => $settings->exists ? $settings->id : null,
        ]);

        $this->ensureBackupName($run, $connection, $settings);
        $this->markProgress($run, 3, "Starting backup {$run->backup_name}", CloudBackupRun::STATUS_RUNNING);

        $stagingDir = $this->stagingDirectory($run);

        try {
            $this->markProgress($run, 6, 'Preparing local backup workspace');
            File::ensureDirectoryExists($stagingDir, 0770, true);
            $run->appendLog('Backup started.');
            $this->markProgress($run, 8, 'Connecting to cloud storage');
            $provider = $this->providerFactory->make($connection->provider);
            $folder = $connection->folder_path ?: 'LaravelBackups';
            $folderId = $provider->ensureFolder($connection, $folder);
            $this->markProgress($run, 15, 'Preparing backup files');

            $artifacts = $this->createArtifacts($settings, $run, $stagingDir);
            $this->markProgress($run, 55, 'Uploading backup files');
            $bytesUploaded = 0;
            $artifactCount = max(1, $artifacts->count());
            $artifactIndex = 0;

            foreach ($artifacts as $artifact) {
                $artifactIndex++;
                $run->appendLog("Uploading {$artifact->filename} to {$connection->provider}.");
                $remote = $provider->uploadFile($connection, $artifact->local_path, $artifact->filename, $folderId);
                $artifact->forceFill([
                    'remote_path' => $remote['path'],
                    'remote_id' => $remote['id'],
                    'status' => 'uploaded',
                ])->save();
                $bytesUploaded += $remote['size'];
                $this->markProgress($run, 55 + (int) floor(($artifactIndex / $artifactCount) * 30), "Uploaded {$artifact->filename}");
            }

            $this->markProgress($run, 90, 'Applying retention policy');
            $deleted = $this->retentionManager->prune($provider, $connection, $folderId, (int) $settings->retention_count);
            if ($deleted > 0) {
                $run->appendLog("Pruned {$deleted} old remote backup files.");
            }

            $run->forceFill([
                'status' => CloudBackupRun::STATUS_SUCCEEDED,
                'progress_percent' => 100,
                'current_step' => 'Backup completed successfully',
                'finished_at' => now(),
                'bytes_uploaded' => $bytesUploaded,
                'manifest' => [
                    'backup_name' => $run->backup_name,
                    'artifacts' => $artifacts->map(fn (CloudBackupArtifact $artifact): array => [
                        'type' => $artifact->type,
                        'filename' => $artifact->filename,
                        'size' => $artifact->size,
                        'checksum' => $artifact->checksum,
                        'remote_path' => $artifact->remote_path,
                    ])->values()->all(),
                ],
            ])->save();

            $this->sendSuccessNotification($run, $connection, $bytesUploaded);

            if ($settings->exists) {
                $settings->forceFill([
                    'last_run_at' => now(),
                    'next_run_at' => $settings->calculateNextRun(),
                ])->save();
            }

            $run->appendLog('Backup completed successfully.');
        } catch (\Throwable $throwable) {
            $run->forceFill([
                'status' => CloudBackupRun::STATUS_FAILED,
                'current_step' => 'Backup failed',
                'finished_at' => now(),
                'error_summary' => $this->safeError($throwable),
            ])->save();

            $connection->forceFill(['last_error' => $this->safeError($throwable)])->save();
            $run->appendLog('Backup failed: '.$this->safeError($throwable));

            throw $throwable;
        } finally {
            if ((bool) config('cloud-backup.archive.cleanup_local_after_upload', true)) {
                File::deleteDirectory($stagingDir);
            }
        }

        return $run;
    }

    /**
     * @return \Illuminate\Support\Collection<int, CloudBackupArtifact>
     */
    private function createArtifacts(CloudBackupSchedule $schedule, CloudBackupRun $run, string $stagingDir)
    {
        $artifacts = collect();
        $prefix = $run->backup_name ?: $this->fallbackBackupName($run);

        if ($schedule->include_files) {
            $this->markProgress($run, 25, 'Creating file archive');
            $filename = "{$prefix}_files.zip";
            $path = $stagingDir.DIRECTORY_SEPARATOR.$filename;
            $this->fileArchive->createArchive(
                $run,
                $schedule->source_path ?: config('cloud-backup.default_source_path', base_path()),
                $path,
                $schedule->exclude_paths ?: []
            );
            $artifacts->push($this->recordArtifact($run, 'files', $filename, $path));
        }

        if ($schedule->include_database) {
            $this->markProgress($run, 45, 'Creating database dump');
            $filename = "{$prefix}_database.sql";
            $path = $stagingDir.DIRECTORY_SEPARATOR.$filename;
            $this->databaseDump->dump($schedule->database_connection ?: config('cloud-backup.database.connection', 'mysql'), $path);
            $artifacts->push($this->recordArtifact($run, 'database', $filename, $path));
        }

        if ($artifacts->isEmpty()) {
            throw new \RuntimeException('Backup schedule has neither file nor database backup enabled.');
        }

        return $artifacts;
    }

    private function recordArtifact(CloudBackupRun $run, string $type, string $filename, string $path): CloudBackupArtifact
    {
        return CloudBackupArtifact::create([
            'run_id' => $run->id,
            'type' => $type,
            'filename' => $filename,
            'local_path' => $path,
            'size' => filesize($path) ?: 0,
            'checksum' => hash_file('sha256', $path) ?: null,
            'status' => 'created',
        ]);
    }

    private function stagingDirectory(CloudBackupRun $run): string
    {
        return storage_path('app/'.trim(config('cloud-backup.staging_path', 'cloud-backups/staging'), '/').'/run-'.$run->id);
    }

    private function ensureBackupName(CloudBackupRun $run, CloudBackupConnection $connection, CloudBackupSchedule $settings): void
    {
        if (filled($run->backup_name)) {
            return;
        }

        $run->forceFill([
            'backup_name' => $this->generateBackupName($run, $connection, $settings),
        ])->save();
    }

    private function generateBackupName(CloudBackupRun $run, CloudBackupConnection $connection, CloudBackupSchedule $settings): string
    {
        $timestamp = ($run->created_at ?: now())
            ->copy()
            ->timezone(config('app.timezone', 'UTC'))
            ->format('Ymd_His');
        $app = Str::slug(config('app.name', 'laravel'), '_') ?: 'laravel';
        $mode = $settings->exists ? 'scheduled' : 'manual';
        $provider = Str::slug($connection->provider, '_') ?: 'cloud';
        $runId = str_pad((string) $run->id, 6, '0', STR_PAD_LEFT);
        $schedule = $settings->exists ? '_'.Str::limit(Str::slug($settings->name, '_'), 40, '') : '';

        return Str::limit("backup_{$timestamp}_run{$runId}_{$app}_{$mode}_{$provider}{$schedule}", 190, '');
    }

    private function fallbackBackupName(CloudBackupRun $run): string
    {
        $timestamp = ($run->created_at ?: now())->format('Ymd_His');
        $runId = str_pad((string) $run->id, 6, '0', STR_PAD_LEFT);

        return "backup_{$timestamp}_run{$runId}_".(Str::slug(config('app.name', 'laravel'), '_') ?: 'laravel');
    }

    private function markProgress(CloudBackupRun $run, int $percent, string $step, ?string $status = null): void
    {
        $run->forceFill(array_filter([
            'status' => $status,
            'progress_percent' => max(0, min(100, $percent)),
            'current_step' => $step,
            'started_at' => $run->started_at ?: now(),
        ], fn ($value): bool => $value !== null))->save();
    }

    private function safeError(\Throwable $throwable): string
    {
        return Str::limit(preg_replace('/(access_token|refresh_token|client_secret)=?[^\\s&]+/i', '$1=[redacted]', $throwable->getMessage()) ?: 'Backup failed.', 500);
    }

    private function sendSuccessNotification(CloudBackupRun $run, CloudBackupConnection $connection, int $bytesUploaded): void
    {
        if (! $run->initiated_by_user_id || $run->schedule_id) {
            return;
        }

        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->find($run->initiated_by_user_id);

        if (! $user) {
            return;
        }

        try {
            $user->notifyNow(Notification::make()
                ->success()
                ->title('Manual backup completed')
                ->body(sprintf(
                    '%s finished successfully on %s. Uploaded %s MB.',
                    $run->backup_name ?: 'Manual backup',
                    $connection->name,
                    number_format($bytesUploaded / 1048576, 2)
                ))
                ->toDatabase());
        } catch (\Throwable $throwable) {
            Log::warning('Cloud backup success notification could not be sent.', [
                'run_id' => $run->id,
                'user_id' => $run->initiated_by_user_id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
