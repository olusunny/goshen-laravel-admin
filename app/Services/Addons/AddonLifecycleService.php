<?php

namespace App\Services\Addons;

use App\Contracts\Addons\AddonLifecycleHandler;
use App\Models\Addon;
use App\Models\AddonInstallLog;
use App\Models\AddonUpdateBackup;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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
        $rollback = null;

        try {
            // Add-on migrations can execute DDL, which implicitly commits on MySQL.
            $existing = Addon::query()->where('package_key', $packageKey)->first();
            if ($existing && ! in_array($existing->status, [Addon::STATUS_UNINSTALLED, Addon::STATUS_UPDATE_FAILED], true)) {
                return $this->updateInstalledAddon($existing, $zipPath, $inspection, $manifest, $signature, $packageKey, $version, $stagingPath, $installPath, $admin, $rollback);
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

            if ($this->activateOnInstall($manifest)) {
                $this->activate($addon->fresh(), $admin);
            } else {
                $this->runtimeLoader->refreshActiveAddonCache();
                $this->log($addon, $packageKey, 'install', 'successful', 'Add-on installed and awaiting explicit activation.', [], $admin);
            }

            return $addon->fresh();
        } catch (Throwable $exception) {
            if ($rollback !== null) {
                $this->restoreFailedUpdate($rollback, $admin, $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param array{manifest: array<string, mixed>, checksum: string, root: string|null} $inspection
     * @param array<string, mixed> $manifest
     * @param array{verified: bool|null, method: string|null, key_id: string|null} $signature
     */
    private function updateInstalledAddon(
        Addon $addon,
        string $zipPath,
        array $inspection,
        array $manifest,
        array $signature,
        string $packageKey,
        string $version,
        string $stagingPath,
        string $installPath,
        ?User $admin,
        ?array &$rollback,
    ): Addon {
        $currentVersion = (string) $addon->installed_version;
        if ($currentVersion !== '' && version_compare($version, $currentVersion, '<=')) {
            throw new RuntimeException("This add-on is already installed at version {$currentVersion}. Upload a newer version to update it.");
        }

        $wasActive = $addon->status === Addon::STATUS_ACTIVE;
        $backupPath = storage_path('app/'.trim(config('addons.storage.backups_path'), '/').'/'.$packageKey.'-'.$currentVersion.'-to-'.$version.'-'.Str::ulid());

        $rollback = [
            'addon_id' => $addon->getKey(),
            'attributes' => $this->snapshotAttributes($addon),
            'backup_path' => $backupPath,
            'install_path' => $installPath,
            'staging_path' => $stagingPath,
            'package_key' => $packageKey,
            'from_version' => $currentVersion,
            'to_version' => $version,
            'backup_created' => false,
            'runtime_gated' => false,
        ];

        try {
            $this->log($addon, $packageKey, 'update', 'running', 'Validating add-on update ZIP.', ['zip' => $zipPath], $admin);
            $this->zips->extractToStaging($zipPath, $stagingPath);

            // Remove the active release from discovery before its directory can move.
            if ($wasActive) {
                $addon->forceFill(['status' => Addon::STATUS_INSTALLED])->save();
                $this->runtimeLoader->refreshActiveAddonCache();
                $rollback['runtime_gated'] = true;
            }

            if (File::exists($installPath)) {
                File::ensureDirectoryExists(dirname($backupPath));
                $this->moveDirectoryOrFail($installPath, $backupPath, true);
                $rollback['backup_created'] = true;

                AddonUpdateBackup::query()->create([
                    'addon_id' => $addon->id,
                    'from_version' => $currentVersion,
                    'to_version' => $version,
                    'backup_path' => $backupPath,
                    'created_by' => $admin?->id,
                ]);
            }

            File::ensureDirectoryExists(dirname($installPath));
            $this->moveDirectoryOrFail($stagingPath, $installPath, true);

            $addon->forceFill([
                'composer_name' => $manifest['composer_name'] ?? null,
                'name' => $manifest['name'],
                'description' => $manifest['description'] ?? null,
                'installed_version' => $version,
                'available_version' => null,
                'status' => $wasActive ? Addon::STATUS_ACTIVE : Addon::STATUS_INSTALLED,
                'provider_class' => $manifest['provider'] ?? null,
                'namespace' => $manifest['namespace'] ?? null,
                'autoload_psr4' => $manifest['autoload_psr4'] ?? [],
                'manifest' => $manifest,
                'install_path' => $installPath,
                'uploaded_zip_path' => $zipPath,
                'checksum' => $inspection['checksum'],
                'signature_verified' => $signature['verified'],
                'updated_by' => $admin?->id,
            ])->save();

            if ($wasActive) {
                $this->activate($addon->fresh(), $admin);
            } else {
                $this->runtimeLoader->registerAddon($manifest, $installPath);
                $this->runSetupTasks($addon->fresh(), $manifest, $installPath, $admin);
                $this->runtimeLoader->refreshActiveAddonCache();
            }

            $this->log($addon->fresh(), $packageKey, 'update', 'successful', "Add-on updated to {$version}.", ['backup_path' => $backupPath], $admin);

            return $addon->fresh();
        } catch (Throwable $exception) {
            if (! $rollback['backup_created'] && ! $rollback['runtime_gated']) {
                $rollback = null;
            }

            throw $exception;
        }
    }

    /**
     * @param array{addon_id: int, attributes: array<string, mixed>, backup_path: string, install_path: string, staging_path: string, package_key: string, from_version: string, to_version: string, backup_created: bool, runtime_gated: bool} $rollback
     */
    private function restoreFailedUpdate(array $rollback, ?User $admin, Throwable $updateFailure): void
    {
        try {
            $installPath = $rollback['install_path'];
            $quarantinePath = dirname($installPath).DIRECTORY_SEPARATOR.'.'.basename($installPath).'.failed-'.Str::ulid();

            if ($rollback['backup_created']) {
                if (File::isDirectory($installPath)) {
                    $this->moveDirectoryOrFail($installPath, $quarantinePath);
                }

                if (File::exists($installPath)) {
                    throw new RuntimeException('The failed add-on files could not be quarantined before rollback.');
                }

                $this->restoreBackupDirectory($rollback['backup_path'], $installPath);
            }

            $attributes = $rollback['attributes'];
            unset($attributes['id']);

            $restored = Addon::query()->whereKey($rollback['addon_id'])->first();
            if (! $restored) {
                throw new RuntimeException('The previous add-on metadata could not be found during rollback.');
            }

            Addon::query()->whereKey($restored->getKey())->update($attributes);
            $restored->refresh();
            $this->runtimeLoader->refreshActiveAddonCache();

            if (File::isDirectory($rollback['staging_path']) && ! File::deleteDirectory($rollback['staging_path'])) {
                throw new RuntimeException('The failed add-on staging directory could not be removed after rollback.');
            }

            if (File::isDirectory($quarantinePath) && ! File::deleteDirectory($quarantinePath)) {
                throw new RuntimeException('The failed replacement files could not be removed after rollback.');
            }

            $this->log(
                $restored,
                $rollback['package_key'],
                'rollback',
                'successful',
                "Add-on update to {$rollback['to_version']} was rolled back to {$rollback['from_version']}.",
                [
                    'backup_path' => $rollback['backup_path'],
                    'quarantine_path' => $quarantinePath,
                    'failed_version' => $rollback['to_version'],
                    'failure' => $updateFailure->getMessage(),
                ],
                $admin,
            );
        } catch (Throwable $rollbackFailure) {
            try {
                Addon::query()
                    ->whereKey($rollback['addon_id'])
                    ->update(['status' => Addon::STATUS_INSTALLED]);
                $this->runtimeLoader->refreshActiveAddonCache();
            } catch (Throwable $gateFailure) {
                $rollbackFailure = new RuntimeException(
                    $rollbackFailure->getMessage().' Runtime gating also failed: '.$gateFailure->getMessage(),
                    previous: $rollbackFailure,
                );
            }

            $this->log(
                null,
                $rollback['package_key'],
                'rollback',
                'failed',
                'Add-on update rollback failed: '.$rollbackFailure->getMessage(),
                [
                    'backup_path' => $rollback['backup_path'],
                    'failed_version' => $rollback['to_version'],
                    'update_failure' => $updateFailure->getMessage(),
                ],
                $admin,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotAttributes(Addon $addon): array
    {
        return $addon->getAttributes();
    }

    protected function moveDirectoryOrFail(string $from, string $to, bool $overwrite = false): void
    {
        if (! File::moveDirectory($from, $to, $overwrite)) {
            throw new RuntimeException("Unable to move add-on directory from [{$from}] to [{$to}].");
        }
    }

    private function restoreBackupDirectory(string $backupPath, string $installPath): void
    {
        try {
            $this->moveDirectoryOrFail($backupPath, $installPath);

            return;
        } catch (Throwable $moveFailure) {
            if (! File::isDirectory($backupPath)) {
                throw new RuntimeException('The add-on backup is unavailable for copy recovery.', previous: $moveFailure);
            }

            if (File::exists($installPath) && ! File::deleteDirectory($installPath)) {
                throw new RuntimeException('The partial add-on restore could not be removed before copy recovery.', previous: $moveFailure);
            }

            try {
                $this->copyDirectoryOrFail($backupPath, $installPath);
            } catch (Throwable $copyFailure) {
                throw new RuntimeException('The add-on backup could not be restored by move or copy.', previous: $copyFailure);
            }
        }
    }

    protected function copyDirectoryOrFail(string $from, string $to): void
    {
        if (! File::copyDirectory($from, $to) || $this->directoryHashes($from) !== $this->directoryHashes($to)) {
            throw new RuntimeException("Unable to copy add-on directory from [{$from}] to [{$to}].");
        }
    }

    /**
     * @return array<string, string>
     */
    private function directoryHashes(string $path): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $hashes = [];
        foreach (File::allFiles($path) as $file) {
            $hashes[str_replace('\\', '/', $file->getRelativePathname())] = hash_file('sha256', $file->getPathname());
        }

        ksort($hashes);

        return $hashes;
    }

    public function activate(Addon $addon, ?User $admin = null): Addon
    {
        $manifest = is_array($addon->manifest) ? $addon->manifest : [];
        $installPath = (string) $addon->install_path;
        if ($manifest === [] || $installPath === '') {
            throw new RuntimeException('The add-on package files or manifest are unavailable for activation.');
        }

        $this->runtimeLoader->registerAddon($manifest, $installPath);
        $this->runSetupTasks($addon, $manifest, $installPath, $admin);

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

    /**
     * Existing manifests predate this field and must keep their install-and-activate behavior.
     *
     * @param array<string, mixed> $manifest
     */
    private function activateOnInstall(array $manifest): bool
    {
        return ! array_key_exists('activate_on_install', $manifest)
            || $manifest['activate_on_install'] === true;
    }

    public function deactivate(Addon $addon, ?User $admin = null): Addon
    {
        $this->runLifecycleHook($addon, 'deactivate');

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
            $this->runLifecycleHook($addon, 'uninstall');

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

    /**
     * @param 'deactivate'|'uninstall' $operation
     */
    private function runLifecycleHook(Addon $addon, string $operation): void
    {
        $manifest = is_array($addon->manifest) ? $addon->manifest : [];
        $handler = $manifest['lifecycle_handler'] ?? null;

        if (! is_string($handler) || $handler === '') {
            return;
        }

        $namespace = $manifest['namespace'] ?? null;
        if (! is_string($namespace) || ! str_starts_with($handler, $namespace)) {
            throw new RuntimeException('The add-on lifecycle handler is not inside the declared add-on namespace.');
        }

        $installPath = $addon->install_path;
        if (! is_string($installPath) || $installPath === '' || ! is_dir($installPath)) {
            throw new RuntimeException('The add-on lifecycle handler cannot be loaded because package files are unavailable.');
        }

        $this->runtimeLoader->registerAddon($manifest, $installPath);

        if (! class_exists($handler) || ! is_a($handler, AddonLifecycleHandler::class, true)) {
            throw new RuntimeException('The add-on lifecycle handler must implement the host lifecycle contract.');
        }

        app($handler)->{$operation}();
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
