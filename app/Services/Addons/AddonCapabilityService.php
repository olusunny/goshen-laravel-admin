<?php

namespace App\Services\Addons;

use App\Models\Addon;
use App\Models\MobileUser;
use Throwable;

class AddonCapabilityService
{
    /**
     * Returns only capabilities contributed by active add-ons. Permission values are
     * filtered for the authenticated mobile user; feature endpoints remain authoritative.
     *
     * @return array<int, array{key: string, permissions: array<int, string>}>
     */
    public function forMobileUser(MobileUser $user): array
    {
        if (! config('addons.enabled', true)) {
            return [];
        }

        $capabilities = [];

        Addon::query()
            ->where('status', Addon::STATUS_ACTIVE)
            ->orderBy('package_key')
            ->get(['id', 'manifest'])
            ->each(function (Addon $addon) use (&$capabilities, $user): void {
                foreach ($this->capabilitiesFromManifest((array) $addon->manifest) as $key => $permissions) {
                    $capabilities[$key] ??= [];
                    $capabilities[$key] = array_values(array_unique([
                        ...$capabilities[$key],
                        ...array_values(array_filter($permissions, fn (string $permission): bool => $this->hasPermission($user, $permission))),
                    ]));
                }
            });

        ksort($capabilities);

        return collect($capabilities)
            ->map(fn (array $permissions, string $key): array => [
                'key' => $key,
                'permissions' => $permissions,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, array<int, string>>
     */
    private function capabilitiesFromManifest(array $manifest): array
    {
        $definitions = $manifest['capabilities'] ?? [];
        if (! is_array($definitions) || array_is_list($definitions)) {
            return [];
        }

        $capabilities = [];
        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            $permissions = $definition['permissions'] ?? [];
            if (! is_array($permissions)) {
                continue;
            }

            $capabilities[$key] = array_values(array_filter($permissions, 'is_string'));
        }

        return $capabilities;
    }

    private function hasPermission(MobileUser $user, string $permission): bool
    {
        try {
            if ($user->hasRole('super_admin')) {
                return true;
            }

            return $user->can($permission);
        } catch (Throwable) {
            return false;
        }
    }
}
