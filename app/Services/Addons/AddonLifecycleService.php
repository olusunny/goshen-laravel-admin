<?php

namespace App\Services\Addons;

use App\Models\Addon;
use App\Models\AddonInstallLog;
use App\Models\AddonUpdateBackup;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AddonLifecycleService
{
    public function __construct(
        private readonly AddonZipInspector $zips,
        private readonly AddonRuntimeLoader $runtimeLoader,
        private readonly AddonSignatureVerifier $signatures,
    ) {}

    public function installFromZip(string $zipPath, ?User $admin = null): Addon
    {
        $inspection = $this->zips->inspect($zipPath);
        $manifest = $inspection['manifest'];
        $signature = $this->signatures->verify($zipPath, $inspection);
        $packageKey = (string) $manifest['package_key'];
        $version = (string) $manifest['version'];
        $stagingPath = storage_path('app/'.trim(config('addons.storage.staging_path'), '/').'/'.$packageKey.'-'.Str::ulid());
        $installPath = base_path(trim(config('addons.install_path'), '/').'/'.$packageKey);

        return DB::transaction(function () use ($zipPath, $inspection, $manifest, $signature, $packageKey, $version, $stagingPath, $installPath, $admin): Addon {
            $existing = Addon::query()->where('package_key', $packageKey)->lockForUpdate()->first();
            if ($existing && ! in_array($existing->status, [Addon::STATUS_UNINSTALLED, Addon::STATUS_UPDATE_FAILED], true)) {
                throw new RuntimeException('This add-on is already installed. Upload a newer ZIP to update it.');
            }

            $this->log(null, $packageKey, 'validate', 'running', 'Validating add-on ZIP.', ['zip' => $zipPath], $admin);
            $this->zips->extractToStaging($zipPath, $stagingPath);

            $this->log(null, $packageKey, 'install', 'running', 'Installing add-on files.', ['staging' => $stagingPath], $admin);
            File::ensureDirectoryExists(dirname($installPath));
            if (File::exists($installPath)) {
                File::deleteDirectory($installPath);
            }
            File::moveDirectory($stagingPath, $installPath, true);

            $addon = Addon::query()->updateOrCreate(
                ['package_key' => $packageKey],
                [
                    'composer_name' => $manifest['composer_name'] ?? null,
                    'name' => $manifest['name'],
                    'description' => $manifest['description'] ?? null,
                    'installed_version' => $version,
                    'available_version' => null,
                    'status' => Addon::STATUS_INSTALLED,
                    'provider_class' => $manifest['provider'] ?? null,
                    'namespace' => $manifest['namespace'] ?? null,
                    'autoload_psr4' => $manifest['autoload_psr4'] ?? [],
                    'manifest' => $manifest,
                    'install_path' => $installPath,
                    'uploaded_zip_path' => $zipPath,
                    'checksum' => $inspection['checksum'],
                    'signature_verified' => $signature['verified'],
                    'installed_by' => $admin?->id,
                    'installed_at' => now(),
                    'uninstalled_at' => null,
                ],
            );

            $this->runtimeLoader->registerAddon($manifest, $installPath);
            $this->runSetupTasks($addon, $manifest, $installPath, $admin);
            $this->activate($addon->fresh(), $admin);

            return $addon->fresh();
        });
    }

    public function activate(Addon $addon, ?User $admin = null): Addon
    {
        $addon->forceFill([
            'status' => Addon::STATUS_ACTIVE,
            'activated_by' => $admin?->id,
            'activated_at' => now(),
            'deactivated_at' => null,
        ])->save();

        $this->log($addon, $addon->package_key, 'activate', 'successful', 'Add-on activated.', [], $admin);
        $this->clearCaches($addon, $admin);
        $this->runtimeLoader->refreshActiveAddonCache();

        return $addon->fresh();
    }

    public function deactivate(Addon $addon, ?User $admin = null): Addon
    {
        $addon->forceFill([
            'status' => Addon::STATUS_INACTIVE,
            'deactivated_at' => now(),
        ])->save();

        $this->log($addon, $addon->package_key, 'deactivate', 'successful', 'Add-on deactivated without deleting data.', [], $admin);
        $this->clearCaches($addon, $admin);
        $this->runtimeLoader->refreshActiveAddonCache();

        return $addon->fresh();
    }

    public function uninstall(Addon $addon, ?User $admin = null, bool $removeFiles = true): Addon
    {
        try {
            if ($removeFiles && $deletePath = $this->safeInstallPathForDeletion($addon)) {
                File::deleteDirectory($deletePath);
            }

            $addon->forceFill([
                'status' => Addon::STATUS_UNINSTALLED,
                'uninstalled_by' => $admin?->id,
                'uninstalled_at' => now(),
                'deactivated_at' => now(),
            ])->save();

            $this->log($addon, $addon->package_key, 'uninstall', 'successful', 'Add-on uninstalled. Package data was preserved by default.', ['remove_files' => $removeFiles], $admin);
            $this->clearCaches($addon, $admin);
            $this->runtimeLoader->refreshActiveAddonCache();

            return $addon->fresh();
        } catch (Throwable $exception) {
            $addon->forceFill(['status' => Addon::STATUS_UNINSTALL_FAILED])->save();
            $this->log($addon, $addon->package_key, 'uninstall', 'failed', $exception->getMessage(), [], $admin);
            throw $exception;
        }
    }

    public function health(Addon $addon, ?User $admin = null): Addon
    {
        $status = $addon->install_path && is_dir($addon->install_path) && is_file($addon->install_path.DIRECTORY_SEPARATOR.'addon.json')
            ? 'healthy'
            : 'missing_files';

        $addon->forceFill([
            'last_health_status' => $status,
            'last_health_check_at' => now(),
        ])->save();

        $this->log($addon, $addon->package_key, 'health_check', $status === 'healthy' ? 'successful' : 'failed', "Health check: {$status}.", [], $admin);

        return $addon->fresh();
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function runSetupTasks(Addon $addon, array $manifest, string $installPath, ?User $admin): void
    {
        $migrationPath = $this->safeManifestRelativePath((string) ($manifest['migrations_path'] ?? ''), 'migrations_path');
        if ($migrationPath !== '' && is_dir($installPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $migrationPath))) {
            $relative = trim(config('addons.install_path'), '/').'/'.$addon->package_key.'/'.$migrationPath;
            $this->callArtisan($addon, 'migrate', ['--path' => $relative, '--force' => true], $admin);
        }

        $namespace = (string) ($manifest['namespace'] ?? '');
        foreach (($manifest['seeders'] ?? []) as $seeder) {
            if (is_string($seeder) && $namespace !== '' && str_starts_with($seeder, $namespace) && class_exists($seeder)) {
                $this->callArtisan($addon, 'db:seed', ['--class' => $seeder, '--force' => true], $admin);
            }
        }

        $this->clearCaches($addon, $admin);
    }

    private function safeManifestRelativePath(string $path, string $field): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized)
            || str_contains($normalized, "\0")) {
            throw new RuntimeException("The manifest path [{$field}] is not safe.");
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException("The manifest path [{$field}] is not safe.");
            }
        }

        return $normalized;
    }

    private function safeInstallPathForDeletion(Addon $addon): ?string
    {
        if (! is_string($addon->install_path) || $addon->install_path === '') {
            return null;
        }

        $target = realpath($addon->install_path);
        if (! is_string($target) || ! is_dir($target)) {
            return null;
        }

        $root = realpath(base_path(trim(config('addons.install_path'), '/')))
            ?: base_path(trim(config('addons.install_path'), '/'));

        $normalizedTarget = rtrim(strtolower(str_replace('\\', '/', $target)), '/');
        $normalizedRoot = rtrim(strtolower(str_replace('\\', '/', $root)), '/');

        if ($normalizedTarget === $normalizedRoot || ! str_starts_with($normalizedTarget, $normalizedRoot.'/')) {
            throw new RuntimeException('The add-on install path is outside the configured add-ons directory.');
        }

        return $target;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function callArtisan(?Addon $addon, string $command, array $parameters, ?User $admin): void
    {
        if (! in_array($command, config('addons.artisan_allowlist', []), true)) {
            throw new RuntimeException("Artisan command [{$command}] is not allowed for add-on lifecycle actions.");
        }

        $exitCode = Artisan::call($command, $parameters);
        $output = Artisan::output();
        $status = $exitCode === 0 ? 'successful' : 'failed';

        $this->log($addon, $addon?->package_key, $command, $status, "Ran artisan {$command}.", ['parameters' => $parameters], $admin, $output);

        if ($exitCode !== 0) {
            throw new RuntimeException("Artisan command [{$command}] failed during add-on setup.");
        }
    }

    private function clearCaches(?Addon $addon = null, ?User $admin = null): void
    {
        foreach (['optimize:clear'] as $command) {
            $this->callArtisan($addon, $command, [], $admin);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(?Addon $addon, ?string $packageKey, string $action, string $status, string $message, array $context = [], ?User $admin = null, ?string $output = null): AddonInstallLog
    {
        return AddonInstallLog::query()->create([
            'addon_id' => $addon?->id,
            'package_key' => $packageKey,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'context' => $context,
            'output' => $output,
            'performed_by' => $admin?->id,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
