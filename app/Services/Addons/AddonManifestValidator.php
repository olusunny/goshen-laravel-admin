<?php

namespace App\Services\Addons;

use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use RuntimeException;

class AddonManifestValidator
{
    /**
     * @return array<string, mixed>
     */
    public function validate(array $manifest): array
    {
        foreach ($this->requiredKeys() as $key) {
            if (! filled(data_get($manifest, $key))) {
                throw new RuntimeException("The add-on manifest is missing required field [{$key}].");
            }
        }

        $packageKey = (string) $manifest['package_key'];
        if (! preg_match('/^[a-z0-9][a-z0-9._-]+[a-z0-9]$/', $packageKey)) {
            throw new RuntimeException('The add-on package_key must use lowercase letters, numbers, dots, dashes, or underscores.');
        }

        $version = (string) $manifest['version'];
        if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new RuntimeException('The add-on version must use semantic versioning, for example 1.0.0.');
        }

        $provider = (string) $manifest['provider'];
        if (! Str::endsWith($provider, 'ServiceProvider') || ! str_contains($provider, '\\')) {
            throw new RuntimeException('The manifest provider must be a fully-qualified Laravel service provider class.');
        }

        $namespace = (string) $manifest['namespace'];
        if (! str_ends_with($namespace, '\\')) {
            throw new RuntimeException('The manifest namespace must end with a namespace separator.');
        }

        $autoload = $manifest['autoload_psr4'] ?? null;
        if (! is_array($autoload) || $autoload === []) {
            throw new RuntimeException('The manifest must declare at least one PSR-4 autoload mapping.');
        }

        foreach ($autoload as $prefix => $path) {
            if (! is_string($prefix) || ! str_ends_with($prefix, '\\')) {
                throw new RuntimeException('Every autoload_psr4 key must be a namespace prefix ending with a namespace separator.');
            }

            if (! is_string($path) || $this->isUnsafeRelativePath($path)) {
                throw new RuntimeException("The autoload path [{$path}] is not safe.");
            }
        }

        $this->validateOptionalRelativePath($manifest, 'migrations_path');
        $this->validateSeeders($manifest, $namespace);
        $this->validateActivateOnInstall($manifest);
        $this->validateCapabilities($manifest);

        $this->assertCompatible(
            (string) ($manifest['minimum_php'] ?? config('addons.compatibility.minimum_php')),
            (string) ($manifest['minimum_laravel'] ?? config('addons.compatibility.minimum_laravel')),
            $manifest['maximum_laravel'] ?? null,
        );

        return $manifest;
    }

    /**
     * @return array<int, string>
     */
    private function requiredKeys(): array
    {
        return [
            'schema_version',
            'package_key',
            'composer_name',
            'name',
            'version',
            'provider',
            'namespace',
            'autoload_psr4',
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function validateOptionalRelativePath(array $manifest, string $key): void
    {
        $path = $manifest[$key] ?? null;

        if ($path === null || $path === '') {
            return;
        }

        if (! is_string($path) || $this->isUnsafeRelativePath($path)) {
            throw new RuntimeException("The manifest path [{$key}] is not safe.");
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function validateSeeders(array $manifest, string $namespace): void
    {
        $seeders = $manifest['seeders'] ?? [];

        if ($seeders === null) {
            return;
        }

        if (! is_array($seeders)) {
            throw new RuntimeException('The manifest seeders field must be an array.');
        }

        foreach ($seeders as $seeder) {
            if (! is_string($seeder) || ! str_starts_with($seeder, $namespace)) {
                throw new RuntimeException('Every manifest seeder must be inside the add-on namespace.');
            }
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function validateActivateOnInstall(array $manifest): void
    {
        if (! array_key_exists('activate_on_install', $manifest)) {
            return;
        }

        if (! is_bool($manifest['activate_on_install'])) {
            throw new RuntimeException('The manifest activate_on_install field must be a boolean.');
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function validateCapabilities(array $manifest): void
    {
        if (! array_key_exists('capabilities', $manifest)) {
            return;
        }

        $capabilities = $manifest['capabilities'];
        if (! is_array($capabilities) || array_is_list($capabilities)) {
            throw new RuntimeException('The manifest capabilities field must map each capability key to its permission mapping.');
        }

        foreach ($capabilities as $key => $definition) {
            if (! is_string($key) || ! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key)) {
                throw new RuntimeException('Every manifest capability key must use lowercase letters, numbers, dots, dashes, or underscores.');
            }

            if (! is_array($definition) || array_is_list($definition)) {
                throw new RuntimeException("The manifest capability [{$key}] must define a permission mapping.");
            }

            $permissions = $definition['permissions'] ?? [];
            if (! is_array($permissions) || ! array_is_list($permissions)) {
                throw new RuntimeException("The manifest capability [{$key}] permissions field must be a list.");
            }

            foreach ($permissions as $permission) {
                if (! is_string($permission) || ! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $permission)) {
                    throw new RuntimeException("The manifest capability [{$key}] contains an invalid permission.");
                }
            }

            if (count(array_unique($permissions)) !== count($permissions)) {
                throw new RuntimeException("The manifest capability [{$key}] contains duplicate permissions.");
            }
        }
    }

    private function assertCompatible(string $minimumPhp, string $minimumLaravel, mixed $maximumLaravel): void
    {
        if (version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new RuntimeException("This add-on requires PHP {$minimumPhp} or newer.");
        }

        if (version_compare(Application::VERSION, $minimumLaravel, '<')) {
            throw new RuntimeException("This add-on requires Laravel {$minimumLaravel} or newer.");
        }

        if (is_string($maximumLaravel) && $maximumLaravel !== '' && version_compare(Application::VERSION, $maximumLaravel, '>')) {
            throw new RuntimeException("This add-on supports Laravel up to {$maximumLaravel}.");
        }
    }

    private function isUnsafeRelativePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized)
            || str_contains($normalized, "\0")) {
            return true;
        }

        foreach (explode('/', trim($normalized, '/')) as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }
}
