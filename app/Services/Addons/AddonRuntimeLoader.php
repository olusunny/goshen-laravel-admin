<?php

namespace App\Services\Addons;

use App\Models\Addon;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AddonRuntimeLoader
{
    /**
     * @var array<string, bool>
     */
    private array $registeredProviders = [];

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
            ->map(fn (Addon $addon): array => [
                'package_key' => $addon->package_key,
                'installed_version' => $addon->installed_version,
                'provider_class' => $addon->provider_class,
                'install_path' => $addon->install_path,
                'checksum' => $addon->checksum,
                'signature_verified' => (bool) $addon->signature_verified,
                'manifest' => $addon->manifest,
            ])
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
     * @param array<string, mixed> $manifest
     */
    public function registerAddon(array $manifest, string $installPath): void
    {
        $installPath = $this->safeInstallPath($installPath) ?? '';
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
                $loader->addPsr4((string) $prefix, $path);
            }
        }

        $provider = (string) ($manifest['provider'] ?? '');
        if ($provider !== '' && class_exists($provider) && ! isset($this->registeredProviders[$provider])) {
            app()->register($provider);
            $this->registeredProviders[$provider] = true;
        }
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
            return [];
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        $addons = is_array($payload) ? ($payload['addons'] ?? []) : [];
        if (! is_array($addons)) {
            return [];
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

        if ((bool) config('addons.signatures.required', false) && ! $addon->signature_verified) {
            return null;
        }

        $installPath = $this->safeInstallPath((string) $addon->install_path);
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
