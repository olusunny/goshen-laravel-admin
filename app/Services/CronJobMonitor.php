<?php

namespace App\Services;

use App\Models\CronJobStatus;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CronJobMonitor
{
    public const STATUS_NEVER_RUN = 'never_run';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, array{
     *     label: string,
     *     expression: string,
     *     frequency_label: string,
     *     command: string,
     *     description: string,
     *     expected_every_minutes: int,
     *     grace_minutes?: int,
     *     tracked?: bool
     * }>
     */
    public function definitions(): array
    {
        return [
            'laravel_scheduler' => [
                'label' => 'Laravel scheduler trigger',
                'expression' => '* * * * *',
                'frequency_label' => 'Every minute',
                'command' => $this->schedulerCronCommand(),
                'description' => 'The single cPanel cron entry that wakes Laravel every minute. All other Laravel jobs depend on this running.',
                'expected_every_minutes' => 1,
                'grace_minutes' => 5,
            ],
            'cloud_backup_run_due' => [
                'label' => 'Cloud backup due runner',
                'expression' => '* * * * *',
                'frequency_label' => 'Every minute',
                'command' => 'php artisan cloud-backup:run-due --sync',
                'description' => 'Checks configured Google Drive/OneDrive backup schedules and runs due backups.',
                'expected_every_minutes' => 1,
                'grace_minutes' => 5,
                'tracked' => false,
            ],
            'prayer_purge_expired' => [
                'label' => 'Purge expired prayer wall requests',
                'expression' => '0 * * * *',
                'frequency_label' => 'Hourly',
                'command' => 'php artisan prayer-community:purge-expired --sync',
                'description' => 'Keeps the interactive prayer wall clean by removing expired community requests.',
                'expected_every_minutes' => 60,
                'grace_minutes' => 180,
            ],
            'goshen_pending_payments' => [
                'label' => 'Process pending Goshen payments',
                'expression' => '*/15 * * * *',
                'frequency_label' => 'Every 15 minutes',
                'command' => 'php artisan goshen:process-pending-payments --limit=100',
                'description' => 'Completes pending Goshen payment records after gateway or delayed-payment confirmation.',
                'expected_every_minutes' => 15,
                'grace_minutes' => 45,
            ],
            'goshen_wallet_topups' => [
                'label' => 'Process wallet auto top-ups',
                'expression' => '*/15 * * * *',
                'frequency_label' => 'Every 15 minutes',
                'command' => 'php artisan goshen:process-wallet-topups --limit=50',
                'description' => 'Processes due wallet auto top-up plans where the feature is enabled.',
                'expected_every_minutes' => 15,
                'grace_minutes' => 45,
            ],
            'goshen_refund_reconciliation' => [
                'label' => 'Reconcile pending Goshen refunds',
                'expression' => '*/5 * * * *',
                'frequency_label' => 'Every 5 minutes',
                'command' => 'php artisan goshen:reconcile-refund-pending --limit=100',
                'description' => 'Checks pending refund records and updates their final status.',
                'expected_every_minutes' => 5,
                'grace_minutes' => 15,
            ],
            'goshen_experience_reminders' => [
                'label' => 'Send Goshen experience reminders',
                'expression' => '*/15 * * * *',
                'frequency_label' => 'Every 15 minutes',
                'command' => 'php artisan goshen:process-experience-reminders --limit=100',
                'description' => 'Sends due Goshen experience survey reminders.',
                'expected_every_minutes' => 15,
                'grace_minutes' => 45,
            ],
            'automatic_notifications' => [
                'label' => 'Automatic notifications',
                'expression' => '* * * * *',
                'frequency_label' => 'Every minute',
                'command' => 'AutomaticNotificationService::processDue()',
                'description' => 'Processes due automatic email/in-app notification rules.',
                'expected_every_minutes' => 1,
                'grace_minutes' => 5,
            ],
            'scheduled_inbox_messages' => [
                'label' => 'Scheduled inbox messages',
                'expression' => '* * * * *',
                'frequency_label' => 'Every minute',
                'command' => 'php artisan inbox:send-scheduled --limit=50',
                'description' => 'Sends messages scheduled from the admin messaging tools.',
                'expected_every_minutes' => 1,
                'grace_minutes' => 5,
            ],
        ];
    }

    public function watch(Event $event, string $key): Event
    {
        $event
            ->before(fn () => $this->markStarted($key))
            ->onSuccess(fn () => $this->markFinished($key, 0))
            ->onFailure(fn () => $this->markFinished($key, 1, 'The scheduled command returned a non-zero exit code.'));

        return $event;
    }

    public function markSchedulerHeartbeat(): void
    {
        $this->syncDefinition('laravel_scheduler');
        $this->markStarted('laravel_scheduler');
        $this->markFinished('laravel_scheduler', 0, 'Laravel scheduler cron triggered successfully.');
    }

    public function markStarted(string $key): void
    {
        if (! $this->ready()) {
            return;
        }

        try {
            $this->syncDefinition($key);

            CronJobStatus::query()->updateOrCreate(
                ['key' => $key],
                [
                    'status' => self::STATUS_RUNNING,
                    'last_started_at' => now(),
                    'last_message' => null,
                ],
            );
        } catch (Throwable) {
            // Cron monitoring must never stop the scheduled business job.
        }
    }

    public function markFinished(string $key, int $exitCode = 0, ?string $message = null): void
    {
        if (! $this->ready()) {
            return;
        }

        try {
            $this->syncDefinition($key);

            $status = CronJobStatus::query()->firstOrNew(['key' => $key]);
            $startedAt = $status->last_started_at;
            $finishedAt = now();
            $succeeded = $exitCode === 0;

            $runtimeMs = $startedAt instanceof CarbonInterface
                ? max(0, (int) $startedAt->diffInMilliseconds($finishedAt))
                : null;

            $status->forceFill([
                'status' => $succeeded ? self::STATUS_SUCCEEDED : self::STATUS_FAILED,
                'last_finished_at' => $finishedAt,
                'last_success_at' => $succeeded ? $finishedAt : $status->last_success_at,
                'last_failed_at' => $succeeded ? $status->last_failed_at : $finishedAt,
                'last_runtime_ms' => $runtimeMs,
                'last_exit_code' => $exitCode,
                'run_count' => ((int) $status->run_count) + 1,
                'failure_count' => ((int) $status->failure_count) + ($succeeded ? 0 : 1),
                'last_message' => $message,
            ])->save();
        } catch (Throwable) {
            // Cron monitoring must never stop the scheduled business job.
        }
    }

    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>, commands: array<string, string>}
     */
    public function report(): array
    {
        $this->syncDefinitions();

        $rows = [];
        $summary = [
            'healthy' => 0,
            'warning' => 0,
            'failed' => 0,
            'not_tracked' => 0,
        ];

        foreach ($this->definitions() as $key => $definition) {
            $status = $this->ready()
                ? CronJobStatus::query()->where('key', $key)->first()
                : null;

            $health = $this->healthFor($definition, $status);
            $summary[$health['state']]++;

            $rows[] = [
                'key' => $key,
                'label' => $definition['label'],
                'expression' => $definition['expression'],
                'frequency_label' => $definition['frequency_label'],
                'command' => $definition['command'],
                'description' => $definition['description'],
                'tracked' => $definition['tracked'] ?? true,
                'status' => $status?->status ?? self::STATUS_NEVER_RUN,
                'last_started_at' => $status?->last_started_at,
                'last_finished_at' => $status?->last_finished_at,
                'last_success_at' => $status?->last_success_at,
                'last_failed_at' => $status?->last_failed_at,
                'last_runtime_ms' => $status?->last_runtime_ms,
                'last_exit_code' => $status?->last_exit_code,
                'run_count' => $status?->run_count ?? 0,
                'failure_count' => $status?->failure_count ?? 0,
                'last_message' => $status?->last_message,
                'health' => $health,
            ];
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
            'commands' => $this->manualCommands(),
        ];
    }

    public function syncDefinitions(): void
    {
        foreach (array_keys($this->definitions()) as $key) {
            $this->syncDefinition($key);
        }
    }

    private function syncDefinition(string $key): void
    {
        if (! $this->ready()) {
            return;
        }

        $definition = $this->definitions()[$key] ?? null;

        if (! $definition) {
            return;
        }

        CronJobStatus::query()->updateOrCreate(
            ['key' => $key],
            [
                'label' => $definition['label'],
                'expression' => $definition['expression'],
                'frequency_label' => $definition['frequency_label'],
                'command' => $definition['command'],
                'description' => $definition['description'],
                'metadata' => [
                    'expected_every_minutes' => $definition['expected_every_minutes'],
                    'grace_minutes' => $definition['grace_minutes'] ?? null,
                    'tracked' => $definition['tracked'] ?? true,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{state: string, label: string, detail: string}
     */
    private function healthFor(array $definition, ?CronJobStatus $status): array
    {
        if (($definition['tracked'] ?? true) === false) {
            return [
                'state' => 'not_tracked',
                'label' => 'Configured by package',
                'detail' => 'This package-provided job appears in Laravel schedule:list. Its detailed runs are managed by the cloud backup module.',
            ];
        }

        if (! $status || ! $status->last_success_at) {
            return [
                'state' => 'warning',
                'label' => 'No successful run yet',
                'detail' => 'Wait a few minutes after installing the scheduler cron, then refresh this page.',
            ];
        }

        if ($status->status === self::STATUS_FAILED) {
            return [
                'state' => 'failed',
                'label' => 'Last run failed',
                'detail' => $status->last_message ?: 'The last recorded run did not complete successfully.',
            ];
        }

        $graceMinutes = (int) ($definition['grace_minutes'] ?? max(5, ((int) $definition['expected_every_minutes']) * 3));
        $staleAt = Carbon::now()->subMinutes($graceMinutes);

        if ($status->last_success_at->lt($staleAt)) {
            return [
                'state' => 'warning',
                'label' => 'Stale',
                'detail' => "No successful run in the last {$graceMinutes} minutes.",
            ];
        }

        return [
            'state' => 'healthy',
            'label' => 'Healthy',
            'detail' => 'The job has run within the expected window.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function manualCommands(): array
    {
        return [
            'cpanel_command' => $this->schedulerCronCommand(),
            'cpanel_minute' => '*',
            'cpanel_hour' => '*',
            'cpanel_day' => '*',
            'cpanel_month' => '*',
            'cpanel_weekday' => '*',
            'alternative_command' => $this->schedulerCronCommand(useArtisanPath: true),
        ];
    }

    private function schedulerCronCommand(bool $useArtisanPath = false): string
    {
        $php = env('CPANEL_CRON_PHP_BIN', '/usr/local/bin/php');
        $appDir = env('CPANEL_CRON_APP_DIR', '/home/goshenretreat/apps/portal/current');
        $logFile = env('CPANEL_CRON_LOG_FILE', '/home/goshenretreat/apps/portal/shared/storage/logs/scheduler.log');

        if ($useArtisanPath) {
            return "{$php} {$appDir}/artisan schedule:run >> {$logFile} 2>&1";
        }

        return "cd {$appDir} && {$php} artisan schedule:run >> {$logFile} 2>&1";
    }

    private function ready(): bool
    {
        try {
            return Schema::hasTable('cron_job_statuses');
        } catch (Throwable) {
            return false;
        }
    }
}
