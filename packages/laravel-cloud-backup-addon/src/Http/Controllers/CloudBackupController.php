<?php

namespace ChurchTools\CloudBackup\Http\Controllers;

use ChurchTools\CloudBackup\Jobs\RunBackupJob;
use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Models\CloudBackupOAuthSetting;
use ChurchTools\CloudBackup\Models\CloudBackupRun;
use ChurchTools\CloudBackup\Models\CloudBackupSchedule;
use ChurchTools\CloudBackup\Services\Cloud\CloudProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class CloudBackupController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to($this->consoleUrl());
    }

    public function storeOAuthSettings(Request $request): RedirectResponse
    {
        $data = $request->validate($this->oauthValidationRules());

        $this->saveProviderCredentials('google', [
            'client_id' => $data['google_client_id'] ?? null,
            'client_secret' => $data['google_client_secret'] ?? null,
            'redirect_uri' => $data['google_redirect_uri'] ?? null,
            'clear_secret' => $request->boolean('google_clear_secret'),
        ]);

        $this->saveProviderCredentials('onedrive', [
            'client_id' => $data['onedrive_client_id'] ?? null,
            'client_secret' => $data['onedrive_client_secret'] ?? null,
            'tenant' => $data['onedrive_tenant'] ?? null,
            'redirect_uri' => $data['onedrive_redirect_uri'] ?? null,
            'clear_secret' => $request->boolean('onedrive_clear_secret'),
        ]);

        CloudBackupOAuthSetting::activate($data['active_provider']);

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', 'Cloud backup OAuth settings saved.');
    }

    public function testOAuthSettings(Request $request): RedirectResponse
    {
        $data = $request->validate($this->oauthValidationRules());
        $provider = $data['active_provider'];
        $credentials = $this->credentialsForProvider($data, $provider, $request);

        if (! filled($credentials['client_id'])) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors("{$credentials['label']} client ID is required before testing.");
        }

        if (! filled($credentials['client_secret'])) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors("{$credentials['label']} client secret is required before testing. Paste a secret or keep an existing saved secret.");
        }

        if (! filled($credentials['redirect_uri'])) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors("{$credentials['label']} redirect URI is required before testing.");
        }

        try {
            $result = $provider === 'google'
                ? $this->testGoogleCredentials($credentials)
                : $this->testOneDriveCredentials($credentials);
        } catch (Throwable) {
            $result = [
                'ok' => false,
                'message' => "{$credentials['label']} could not be reached for validation right now. Please check the server internet connection and try again.",
            ];
        }

        return redirect()
            ->to($this->consoleUrl())
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function resetOAuthSettings(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['google', 'onedrive'], true), 404);

        $setting = CloudBackupOAuthSetting::query()->firstOrNew(['provider' => $provider]);
        $setting->forceFill([
            'client_id' => null,
            'client_secret' => null,
            'tenant' => $provider === 'onedrive' ? 'common' : null,
            'redirect_uri' => null,
        ])->save();

        $label = $provider === 'google' ? 'Google Drive' : 'OneDrive';

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', "{$label} saved OAuth credentials have been reset.");
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:cloud_backup_connections,id'],
            'name' => ['required', 'string', 'max:120'],
            'frequency' => ['required', 'in:hourly,daily,weekly,monthly'],
            'include_files' => ['nullable', 'boolean'],
            'include_database' => ['nullable', 'boolean'],
            'source_path' => ['nullable', 'string', 'max:500'],
            'database_connection' => ['nullable', 'string', 'max:100'],
            'exclude_paths' => ['nullable', 'string', 'max:2000'],
            'retention_count' => ['required', 'integer', 'min:1', 'max:100'],
            'schedule_time' => ['nullable', 'date_format:H:i'],
            'schedule_weekday' => ['nullable', 'integer', 'min:0', 'max:6'],
            'schedule_month_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $schedule = CloudBackupSchedule::create([
            'connection_id' => $data['connection_id'],
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'include_files' => $request->boolean('include_files'),
            'include_database' => $request->boolean('include_database'),
            'source_path' => $data['source_path'] ?? null,
            'database_connection' => $data['database_connection'] ?? null,
            'exclude_paths' => $this->linesToArray($data['exclude_paths'] ?? ''),
            'retention_count' => $data['retention_count'],
            'schedule_time' => $data['schedule_time'] ?? '00:30',
            'schedule_weekday' => (int) ($data['schedule_weekday'] ?? 0),
            'schedule_month_day' => (int) ($data['schedule_month_day'] ?? 1),
            'timezone' => $data['timezone'] ?? 'Africa/Lagos',
        ]);

        $schedule->forceFill([
            'next_run_at' => $schedule->calculateNextRun(),
        ])->save();

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', "Backup schedule [{$schedule->name}] created.");
    }

    public function runOnDemand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:cloud_backup_connections,id'],
            'include_files' => ['nullable', 'boolean'],
            'include_database' => ['nullable', 'boolean'],
            'source_path' => ['nullable', 'string', 'max:500'],
            'database_connection' => ['nullable', 'string', 'max:100'],
            'exclude_paths' => ['nullable', 'string', 'max:2000'],
            'retention_count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $includeFiles = $request->boolean('include_files');
        $includeDatabase = $request->boolean('include_database');

        if (! $includeFiles && ! $includeDatabase) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('Choose files, database, or both before starting an on-demand backup.');
        }

        $connection = CloudBackupConnection::query()->findOrFail($data['connection_id']);
        $activeProvider = CloudBackupOAuthSetting::activeProvider();

        if ($connection->provider !== $activeProvider) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('Please choose a connection from the currently selected backup drive.');
        }

        $run = CloudBackupRun::create([
            'connection_id' => $connection->id,
            'schedule_id' => null,
            'status' => CloudBackupRun::STATUS_QUEUED,
            'progress_percent' => 0,
            'current_step' => 'Starting on-demand backup',
            'initiated_by_user_id' => $request->user()?->getKey(),
        ]);

        $started = $this->startOnDemandBackupProcess($run, [
            'include_files' => $includeFiles,
            'include_database' => $includeDatabase,
            'source_path' => $data['source_path'] ?? null,
            'database_connection' => $data['database_connection'] ?? null,
            'exclude_paths' => $this->linesToArray($data['exclude_paths'] ?? ''),
            'retention_count' => (int) $data['retention_count'],
        ]);

        if (! $started) {
            $run->forceFill([
                'status' => CloudBackupRun::STATUS_FAILED,
                'progress_percent' => 100,
                'current_step' => 'Backup process could not be started',
                'finished_at' => now(),
                'error_summary' => 'The server could not start the on-demand backup worker.',
            ])->save();

            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('The server could not start the on-demand backup worker. Please check that PHP CLI execution is enabled.');
        }

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', 'On-demand backup started. Progress will update below while it runs.');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function startOnDemandBackupProcess(CloudBackupRun $run, array $options): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $encodedOptions = base64_encode(json_encode($options, JSON_THROW_ON_ERROR));
        $artisan = base_path('artisan');
        $php = $this->phpCliBinary();
        $log = storage_path("logs/cloud-backup-run-{$run->id}.log");

        $command = escapeshellarg($php)
            .' '.escapeshellarg($artisan)
            .' cloud-backup:run-on-demand '.(int) $run->id
            .' --options='.escapeshellarg($encodedOptions);

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" '.$command.' >> '.escapeshellarg($log).' 2>&1', 'r'));

            return true;
        }

        File::append($log, '['.now()->toDateTimeString()."] Launching on-demand backup run {$run->id}.\n");
        exec('nohup '.$command.' >> '.escapeshellarg($log).' 2>&1 & echo $!', $output, $exitCode);

        return $exitCode === 0 && filled($output[0] ?? null);
    }

    private function phpCliBinary(): string
    {
        $candidates = [];

        if (PHP_BINARY && ! str_contains(strtolower(PHP_BINARY), 'php-fpm')) {
            $candidates[] = PHP_BINARY;
        }

        if (defined('PHP_BINDIR')) {
            $candidates[] = PHP_BINDIR.DIRECTORY_SEPARATOR.'php';
        }

        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = 'php';

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate === 'php' || is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    public function runProgress(CloudBackupRun $run): JsonResponse
    {
        $run->load('connection');

        return response()->json([
            'id' => $run->id,
            'backup_name' => $run->backup_name,
            'status' => $run->status,
            'progress_percent' => (int) ($run->progress_percent ?? 0),
            'current_step' => $run->current_step ?: ucfirst($run->status),
            'bytes_uploaded' => (int) $run->bytes_uploaded,
            'bytes_uploaded_label' => number_format(((int) $run->bytes_uploaded) / 1048576, 2).' MB',
            'error_summary' => $run->error_summary,
            'finished' => in_array($run->status, [
                CloudBackupRun::STATUS_SUCCEEDED,
                CloudBackupRun::STATUS_FAILED,
            ], true),
        ]);
    }

    public function destroyRun(CloudBackupRun $run, CloudProviderFactory $providerFactory): RedirectResponse
    {
        if ($this->backupRunIsActive($run)) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('Running or queued backup runs cannot be deleted yet.');
        }

        try {
            $this->deleteBackupRunWithRemoteArtifacts($run, $providerFactory);
        } catch (Throwable $throwable) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('Unable to delete this backup run: '.$this->safeThrowableMessage($throwable));
        }

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', 'Backup run deleted successfully.');
    }

    public function destroyRuns(Request $request, CloudProviderFactory $providerFactory): RedirectResponse
    {
        $ids = collect($request->input('run_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('Select at least one backup run to delete.');
        }

        $runs = CloudBackupRun::query()
            ->with(['connection', 'artifacts'])
            ->whereIn('id', $ids)
            ->get();

        $activeCount = $runs->filter(fn (CloudBackupRun $run): bool => $this->backupRunIsActive($run))->count();

        if ($activeCount > 0) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('One or more selected backup runs are still queued or running. Wait for them to finish before deleting.');
        }

        try {
            foreach ($runs as $run) {
                $this->deleteBackupRunWithRemoteArtifacts($run, $providerFactory);
            }
        } catch (Throwable $throwable) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('Bulk delete stopped: '.$this->safeThrowableMessage($throwable));
        }

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', "Deleted {$runs->count()} backup run(s) successfully.");
    }

    public function runNow(CloudBackupSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->enabled, 422, 'This schedule is disabled.');

        RunBackupJob::dispatchSync($schedule->id);

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', "Backup schedule [{$schedule->name}] completed.");
    }

    public function destroySchedule(CloudBackupSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->to($this->consoleUrl())->with('status', 'Backup schedule removed.');
    }

    public function destroyConnection(CloudBackupConnection $connection): RedirectResponse
    {
        $connection->delete();

        return redirect()->to($this->consoleUrl())->with('status', 'Cloud connection removed.');
    }

    public function testConnection(CloudBackupConnection $connection, CloudProviderFactory $providerFactory): RedirectResponse
    {
        $activeProvider = CloudBackupOAuthSetting::activeProvider();

        if ($connection->provider !== $activeProvider) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors('Please switch the active backup drive before testing this connection.');
        }

        $provider = $providerFactory->make($connection->provider);
        $folder = trim(($connection->folder_path ?: 'LaravelBackups').'/connection-tests/codex-'.now()->format('Ymd-His').'-'.Str::random(6), '/');
        $tempFile = storage_path('app/cloud-backup-connection-test-'.Str::random(12).'.txt');
        $folderId = null;
        $uploadedId = null;

        try {
            File::put($tempFile, 'Cloud backup connection test '.now()->toIso8601String());

            $folderId = $provider->ensureFolder($connection, $folder);
            $uploaded = $provider->uploadFile($connection, $tempFile, 'connection-test.txt', $folderId);
            $uploadedId = $uploaded['id'] ?? null;

            if ($uploadedId) {
                $provider->deleteFile($connection, $uploadedId);
            }

            if ($folderId) {
                $provider->deleteFile($connection, $folderId);
            }

            $connection->forceFill(['last_error' => null])->save();

            return redirect()
                ->to($this->consoleUrl())
                ->with('status', "Connection test passed for {$connection->name}. A temporary folder and file were created, uploaded, and deleted.");
        } catch (Throwable $throwable) {
            if ($uploadedId) {
                try {
                    $provider->deleteFile($connection, $uploadedId);
                } catch (Throwable) {
                    // Keep the original provider error as the reported failure.
                }
            }

            if ($folderId) {
                try {
                    $provider->deleteFile($connection, $folderId);
                } catch (Throwable) {
                    // Keep the original provider error as the reported failure.
                }
            }

            $message = $this->safeThrowableMessage($throwable);
            $connection->forceFill(['last_error' => $message])->save();

            return redirect()
                ->to($this->consoleUrl())
                ->withErrors("Connection test failed for {$connection->name}: {$message}");
        } finally {
            File::delete($tempFile);
        }
    }

    /**
     * @return array<int, string>
     */
    private function linesToArray(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function oauthConfigured(string $provider): bool
    {
        return CloudBackupOAuthSetting::configured($provider)
            || (filled(config("cloud-backup.oauth.{$provider}.client_id"))
                && filled(config("cloud-backup.oauth.{$provider}.client_secret")));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function oauthValidationRules(): array
    {
        return [
            'active_provider' => ['required', 'in:google,onedrive'],
            'google_client_id' => ['nullable', 'string', 'max:2000'],
            'google_client_secret' => ['nullable', 'string', 'max:4000'],
            'google_redirect_uri' => ['nullable', 'url', 'max:2000'],
            'google_clear_secret' => ['nullable', 'boolean'],
            'onedrive_client_id' => ['nullable', 'string', 'max:2000'],
            'onedrive_client_secret' => ['nullable', 'string', 'max:4000'],
            'onedrive_tenant' => ['nullable', 'string', 'max:255'],
            'onedrive_redirect_uri' => ['nullable', 'url', 'max:2000'],
            'onedrive_clear_secret' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{label: string, client_id: ?string, client_secret: ?string, tenant: ?string, redirect_uri: ?string}
     */
    private function credentialsForProvider(array $data, string $provider, Request $request): array
    {
        $setting = CloudBackupOAuthSetting::forProvider($provider);
        $label = $provider === 'google' ? 'Google Drive' : 'OneDrive';
        $secretCleared = $request->boolean("{$provider}_clear_secret");

        $clientId = trim((string) ($data["{$provider}_client_id"] ?? ''))
            ?: ($setting?->client_id ?: config("cloud-backup.oauth.{$provider}.client_id"));

        $clientSecret = $secretCleared
            ? null
            : (trim((string) ($data["{$provider}_client_secret"] ?? ''))
                ?: ($setting?->client_secret ?: config("cloud-backup.oauth.{$provider}.client_secret")));

        $redirectUri = trim((string) ($data["{$provider}_redirect_uri"] ?? ''))
            ?: ($setting?->redirect_uri
                ?: (config("cloud-backup.oauth.{$provider}.redirect_uri")
                    ?: route('cloud-backup.oauth.callback', ['provider' => $provider])));

        return [
            'label' => $label,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'tenant' => $provider === 'onedrive'
                ? (trim((string) ($data['onedrive_tenant'] ?? '')) ?: ($setting?->tenant ?: config('cloud-backup.oauth.onedrive.tenant', 'common')))
                : null,
            'redirect_uri' => $redirectUri,
        ];
    }

    /**
     * @param array{client_id: ?string, client_secret: ?string, redirect_uri: ?string} $credentials
     * @return array{ok: bool, message: string}
     */
    private function testGoogleCredentials(array $credentials): array
    {
        $response = Http::asForm()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'authorization_code',
                'code' => 'cloud-backup-validation-code',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'redirect_uri' => $credentials['redirect_uri'],
            ]);

        $body = (string) $response->body();

        if (str_contains($body, 'invalid_grant')) {
            return [
                'ok' => true,
                'message' => 'Google Drive OAuth credentials look valid. Google accepted the app credentials and only rejected the test authorization code, which is expected.',
            ];
        }

        if ($response->status() === 401 || str_contains($body, 'invalid_client') || str_contains($body, 'unauthorized_client')) {
            return [
                'ok' => false,
                'message' => 'Google rejected the client ID or client secret. Please confirm the OAuth client is a Web application and the secret is copied correctly.',
            ];
        }

        return [
            'ok' => false,
            'message' => "Google Drive credential test returned HTTP {$response->status()}. Please confirm the client, secret, enabled Drive API, and redirect URI.",
        ];
    }

    /**
     * @param array{client_id: ?string, client_secret: ?string, tenant: ?string, redirect_uri: ?string} $credentials
     * @return array{ok: bool, message: string}
     */
    private function testOneDriveCredentials(array $credentials): array
    {
        $tenant = trim((string) ($credentials['tenant'] ?: 'common'));

        $response = Http::asForm()
            ->timeout(15)
            ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'grant_type' => 'authorization_code',
                'code' => 'cloud-backup-validation-code',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'redirect_uri' => $credentials['redirect_uri'],
            ]);

        $body = (string) $response->body();

        if (str_contains($body, 'invalid_grant') || str_contains($body, 'AADSTS70000')) {
            return [
                'ok' => true,
                'message' => 'OneDrive OAuth credentials look valid. Microsoft accepted the app credentials and only rejected the test authorization code, which is expected.',
            ];
        }

        if ($response->status() === 401
            || str_contains($body, 'invalid_client')
            || str_contains($body, 'AADSTS7000215')
            || str_contains($body, 'AADSTS700016')) {
            return [
                'ok' => false,
                'message' => 'Microsoft rejected the OneDrive client ID, client secret, or tenant. Please confirm the app registration and copied secret value.',
            ];
        }

        return [
            'ok' => false,
            'message' => "OneDrive credential test returned HTTP {$response->status()}. Please confirm the app registration, tenant, secret, and redirect URI.",
        ];
    }

    /**
     * @param array{client_id?: ?string, client_secret?: ?string, tenant?: ?string, redirect_uri?: ?string, clear_secret?: bool} $data
     */
    private function saveProviderCredentials(string $provider, array $data): void
    {
        $setting = CloudBackupOAuthSetting::query()->firstOrNew(['provider' => $provider]);

        $setting->client_id = trim((string) ($data['client_id'] ?? '')) ?: null;
        $setting->tenant = trim((string) ($data['tenant'] ?? '')) ?: ($provider === 'onedrive' ? 'common' : null);
        $setting->redirect_uri = trim((string) ($data['redirect_uri'] ?? '')) ?: null;

        if (($data['clear_secret'] ?? false) === true) {
            $setting->client_secret = null;
        } elseif (filled($data['client_secret'] ?? null)) {
            $setting->client_secret = (string) $data['client_secret'];
        }

        $setting->save();
    }

    private function consoleUrl(): string
    {
        return Route::has('filament.admin.pages.cloud-backup-console')
            ? route('filament.admin.pages.cloud-backup-console')
            : route('cloud-backup.index');
    }

    private function backupRunIsActive(CloudBackupRun $run): bool
    {
        return in_array($run->status, [
            CloudBackupRun::STATUS_QUEUED,
            CloudBackupRun::STATUS_RUNNING,
        ], true);
    }

    private function deleteBackupRunWithRemoteArtifacts(CloudBackupRun $run, CloudProviderFactory $providerFactory): void
    {
        $run->loadMissing(['connection', 'artifacts']);

        $remoteArtifacts = $run->artifacts->filter(fn ($artifact): bool => filled($artifact->remote_id));

        if ($remoteArtifacts->isNotEmpty()) {
            if (! $run->connection) {
                throw new \RuntimeException('The cloud connection for this run no longer exists, so its remote backup files cannot be safely removed.');
            }

            $provider = $providerFactory->make($run->connection->provider);

            foreach ($remoteArtifacts as $artifact) {
                $provider->deleteFile($run->connection, (string) $artifact->remote_id);
            }
        }

        foreach ($run->artifacts as $artifact) {
            if (filled($artifact->local_path) && File::exists($artifact->local_path)) {
                File::delete($artifact->local_path);
            }
        }

        $run->artifacts()->delete();
        $run->delete();
    }

    private function safeThrowableMessage(Throwable $throwable): string
    {
        $message = trim($throwable->getMessage());

        if ($message === '') {
            return 'The cloud provider returned an empty error response.';
        }

        return Str::limit(preg_replace('/\s+/', ' ', $message) ?: $message, 500, '');
    }
}
