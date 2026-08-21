<?php

namespace App\Services\Addons;

use App\Models\Addon;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;
use Throwable;

class AddonRuntimeLoader
{
    /**
     * @var array<string, bool>
     */
    private array $registeredProviders = [];

    /**
     * @var array<string, bool>
     */
    private array $registeredAutoloaders = [];

    public function registerActiveAddons(): void
    {
        if (! config('addons.enabled', true)) {
            return;
        }

        foreach ($this->cachedActiveAddons() as $addon) {
            $manifest = $addon['manifest'] ?? null;
            $installPath = $addon['install_path'] ?? null;

            if (! is_array($manifest) || ! is_string($installPath)) {
                continue;
            }

            $this->registerAddon($manifest, $installPath);
        }
    }

    public function refreshActiveAddonCache(): void
    {
        $addons = Addon::query()
            ->where('status', Addon::STATUS_ACTIVE)
            ->orderBy('package_key')
            ->get()
            ->map(function (Addon $addon): ?array {
                $installPath = $this->resolveInstallPath((string) $addon->package_key, (string) $addon->install_path);
                if ($installPath === null) {
                    return null;
                }

                $this->repairInstallPath($addon, $installPath);

                return [
                    'package_key' => $addon->package_key,
                    'installed_version' => $addon->installed_version,
                    'provider_class' => $addon->provider_class,
                    'install_path' => $installPath,
                    'checksum' => $addon->checksum,
                    'signature_verified' => (bool) $addon->signature_verified,
                    'manifest' => $addon->manifest,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $this->writeActiveAddonCache($addons);
    }

    /**
     * @return array<int, array{path: string, namespace: string, package_key: string}>
     */
    public function filamentResourceDiscoveries(): array
    {
        $discoveries = [];

        foreach ($this->cachedActiveAddons() as $addon) {
            $manifest = $addon['manifest'] ?? null;
            $installPath = $addon['install_path'] ?? null;

            if (! is_array($manifest) || ! is_string($installPath)) {
                continue;
            }

            $namespace = (string) ($manifest['namespace'] ?? '');
            if ($namespace === '') {
                continue;
            }

            $resourcePath = $this->safeAddonChildPath($installPath, 'src/Filament/Resources');
            if ($resourcePath === null || ! is_dir($resourcePath)) {
                continue;
            }

            $discoveries[] = [
                'package_key' => (string) ($addon['package_key'] ?? $manifest['package_key'] ?? ''),
                'path' => $resourcePath,
                'namespace' => rtrim($namespace, '\\').'\\Filament\\Resources',
            ];
        }

        return $discoveries;
    }

    /**
     * @return array<string, string>
     */
    public function adminPermissionLabels(): array
    {
        $labels = [];

        foreach ($this->cachedActiveAddons() as $addon) {
            $manifest = $addon['manifest'] ?? null;
            if (! is_array($manifest)) {
                continue;
            }

            $labels = array_replace(
                $labels,
                $this->permissionLabelsForManifest(
                    $manifest,
                    (string) ($addon['package_key'] ?? 'Add-on'),
                ),
            );
        }

        ksort($labels);

        return $labels;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, string>
     */
    public function permissionLabelsForManifest(array $manifest, string $fallbackName = 'Add-on'): array
    {
        $addonName = trim((string) ($manifest['name'] ?? $fallbackName)) ?: 'Add-on';
        $permissions = collect($manifest['permissions'] ?? [])
            ->filter(fn (mixed $permission): bool => is_string($permission));

        $capabilities = $manifest['capabilities'] ?? [];
        if (is_array($capabilities) && ! array_is_list($capabilities)) {
            foreach ($capabilities as $definition) {
                if (! is_array($definition)) {
                    continue;
                }

                $permissions = $permissions->merge(
                    array_filter($definition['permissions'] ?? [], 'is_string'),
                );
            }
        }

        return $permissions
            ->map(fn (string $permission): string => trim($permission))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $permission): array => [
                $permission => $addonName.' - '.str($permission)
                    ->afterLast('.')
                    ->replace('_', ' ')
                    ->headline()
                    ->toString(),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function registerAddon(array $manifest, string $installPath): void
    {
        $installPath = $this->resolveInstallPath((string) ($manifest['package_key'] ?? ''), $installPath) ?? '';
        if ($installPath === '') {
            return;
        }

        $loader = $this->composerLoader();
        if (! $loader) {
            return;
        }

        foreach (($manifest['autoload_psr4'] ?? []) as $prefix => $relativePath) {
            $path = $this->safeAddonChildPath($installPath, (string) $relativePath);
            if ($path !== null && is_dir($path)) {
                $normalizedPrefix = rtrim((string) $prefix, '\\').'\\';

                $loader->addPsr4($normalizedPrefix, $path);
                $this->registerPsr4FallbackAutoloader($normalizedPrefix, $path);
            }
        }

        $provider = (string) ($manifest['provider'] ?? '');
        if ($provider !== '' && class_exists($provider) && ! isset($this->registeredProviders[$provider])) {
            app()->register($provider);
            $this->registeredProviders[$provider] = true;
        }
    }

    private function registerPsr4FallbackAutoloader(string $prefix, string $path): void
    {
        $key = $prefix.'|'.$path;

        if (isset($this->registeredAutoloaders[$key])) {
            return;
        }

        spl_autoload_register(
            static function (string $class) use ($prefix, $path): void {
                if (! str_starts_with($class, $prefix)) {
                    return;
                }

                $relativeClass = substr($class, strlen($prefix));
                $file = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass).'.php';

                if (is_file($file)) {
                    require $file;
                }
            },
            true,
            true,
        );

        $this->registeredAutoloaders[$key] = true;
    }

    private function composerLoader(): ?ClassLoader
    {
        foreach (spl_autoload_functions() ?: [] as $function) {
            if (is_array($function) && ($function[0] ?? null) instanceof ClassLoader) {
                return $function[0];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cachedActiveAddons(): array
    {
        $path = $this->cachePath();
        if (! is_file($path)) {
            return $this->activeAddonsFromDatabase();
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->activeAddonsFromDatabase();
        }

        $addons = is_array($payload) ? ($payload['addons'] ?? []) : [];
        if (! is_array($addons) || $addons === []) {
            return $this->activeAddonsFromDatabase();
        }

        $trusted = [];
        foreach (array_filter($addons, 'is_array') as $addon) {
            $resolved = $this->trustedActiveAddon($addon);
            if ($resolved !== null) {
                $trusted[] = $resolved;
            }
        }

        return $trusted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeAddonsFromDatabase(): array
    {
        try {
            $addons = Addon::query()
                ->where('status', Addon::STATUS_ACTIVE)
                ->orderBy('package_key')
                ->get();
        } catch (Throwable) {
            return [];
        }

        return $addons
            ->map(fn (Addon $addon): ?array => $this->trustedAddon($addon))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $addons
     */
    private function writeActiveAddonCache(array $addons): void
    {
        $path = $this->cachePath();
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'addons' => $addons,
        ];

        $temporaryPath = $path.'.'.getmypid().'.tmp';
        File::put($temporaryPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        File::move($temporaryPath, $path);
    }

    private function cachePath(): string
    {
        return (string) config('addons.runtime_cache_path', storage_path('app/addons/active-addons.json'));
    }

    /**
     * @param array<string, mixed> $cached
     * @return array<string, mixed>|null
     */
    private function trustedActiveAddon(array $cached): ?array
    {
        $packageKey = (string) ($cached['package_key'] ?? '');
        if ($packageKey === '') {
            return null;
        }

        try {
            $addon = Addon::query()
                ->where('package_key', $packageKey)
                ->where('status', Addon::STATUS_ACTIVE)
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (! $addon instanceof Addon) {
            return null;
        }

        return $this->trustedAddon($addon);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function trustedAddon(Addon $addon): ?array
    {

        if ((bool) config('addons.signatures.required', false) && ! $addon->signature_verified) {
            return null;
        }

        $installPath = $this->resolveInstallPath((string) $addon->package_key, (string) $addon->install_path);
        $manifest = is_array($addon->manifest) ? $addon->manifest : [];
        if ($installPath === null || $manifest === []) {
            return null;
        }

        return [
            'package_key' => $addon->package_key,
            'installed_version' => $addon->installed_version,
            'provider_class' => $addon->provider_class,
            'install_path' => $installPath,
            'checksum' => $addon->checksum,
            'signature_verified' => (bool) $addon->signature_verified,
            'manifest' => $manifest,
        ];
    }

    public function resolveInstallPath(string $packageKey, ?string $recordedPath = null): ?string
    {
        if ($packageKey === ''
            || str_contains($packageKey, "\0")
            || str_contains($packageKey, '/')
            || str_contains($packageKey, '\\')) {
            return null;
        }

        $root = base_path(trim((string) config('addons.install_path', 'addons'), '/\\'));
        $canonicalPath = $this->safeInstallPath($root.DIRECTORY_SEPARATOR.$packageKey);

        return $canonicalPath ?? $this->safeInstallPath((string) $recordedPath);
    }

    private function repairInstallPath(Addon $addon, string $installPath): void
    {
        if ($addon->install_path === $installPath) {
            return;
        }

        try {
            Addon::query()->whereKey($addon->getKey())->update(['install_path' => $installPath]);
            $addon->install_path = $installPath;
        } catch (Throwable) {
            // Loading from the verified shared path is more important than metadata repair.
        }
    }

    private function safeInstallPath(string $path): ?string
    {
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        $target = realpath($path);
        if (! is_string($target) || ! is_dir($target)) {
            return null;
        }

        $root = realpath(base_path(trim((string) config('addons.install_path', 'addons'), '/\\')))
            ?: base_path(trim((string) config('addons.install_path', 'addons'), '/\\'));

        $normalizedTarget = $this->normalizePath($target);
        $normalizedRoot = $this->normalizePath($root);

        if ($normalizedTarget === $normalizedRoot || ! str_starts_with($normalizedTarget, $normalizedRoot.'/')) {
            return null;
        }

        return $target;
    }

    private function safeAddonChildPath(string $installPath, string $relativePath): ?string
    {
        $normalizedRelative = trim(str_replace('\\', '/', $relativePath), '/');
        if ($normalizedRelative === '' || str_contains($normalizedRelative, "\0")) {
            return null;
        }

        foreach (explode('/', $normalizedRelative) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        $path = realpath($installPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedRelative));
        if (! is_string($path)) {
            return null;
        }

        $normalizedPath = $this->normalizePath($path);
        $normalizedInstallPath = $this->normalizePath($installPath);

        return str_starts_with($normalizedPath, $normalizedInstallPath.'/') ? $path : null;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(strtolower(str_replace('\\', '/', $path)), '/');
    }
}
