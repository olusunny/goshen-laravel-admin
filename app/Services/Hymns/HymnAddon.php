<?php

namespace App\Services\Hymns;

use App\Contracts\Hymns\HymnCatalog;
use App\Models\Addon;
use App\Services\Addons\AddonRuntimeLoader;

class HymnAddon
{
    public const PACKAGE_KEY = 'church-tools.digital-hymn-book';

    public const HOST_API_VERSION = 1;

    public const PROVIDER = 'ChurchTools\\DigitalHymnBook\\DigitalHymnBookServiceProvider';

    public function __construct(private readonly AddonRuntimeLoader $runtime) {}

    /** Recheck persisted lifecycle state even when providers/routes are already cached. */
    public function activeAddon(): ?Addon
    {
        if (! config('addons.enabled', true)) {
            return null;
        }

        $addon = Addon::query()->where('package_key', self::PACKAGE_KEY)
            ->where('status', Addon::STATUS_ACTIVE)->first();
        if (! $addon || (config('addons.signatures.required', true) && ! $addon->signature_verified)) {
            return null;
        }
        $manifest = $addon->manifest;
        if (! is_array($manifest) || ($manifest['package_key'] ?? null) !== self::PACKAGE_KEY
            || ($manifest['provider'] ?? null) !== self::PROVIDER) {
            return null;
        }
        $path = $this->runtime->resolveInstallPath(self::PACKAGE_KEY, $addon->install_path);
        if ($path === null || ! is_file($path.'/addon.json')) {
            return null;
        }

        return $addon;
    }

    public function catalog(): ?HymnCatalog
    {
        $addon = $this->activeAddon();
        if (! $addon) {
            return null;
        }
        if (! app()->bound(HymnCatalog::class)) {
            $path = $this->runtime->resolveInstallPath(self::PACKAGE_KEY, $addon->install_path);
            if ($path === null) {
                return null;
            }
            $this->runtime->registerAddon($addon->manifest, $path);
        }

        return app()->bound(HymnCatalog::class) ? app(HymnCatalog::class) : null;
    }

    public function status(): array
    {
        $base = ['schema_version' => 1, 'capability' => 'digital_hymn_book', 'enabled' => false,
            'addon_active' => false, 'reason' => 'not_installed', 'minimum_client_schema' => 1, 'books' => []];
        if (! config('addons.enabled', true)) {
            return [...$base, 'reason' => 'disabled'];
        }

        $record = Addon::query()->where('package_key', self::PACKAGE_KEY)->first();
        if (! $record) {
            return $base;
        }
        if (! $record->isActive()) {
            return [...$base, 'reason' => $record->status === Addon::STATUS_UNINSTALLED ? 'not_installed' : 'inactive'];
        }
        $catalog = $this->catalog();
        if (! $catalog) {
            return [...$base, 'reason' => 'package_unavailable'];
        }

        // Transport/database errors deliberately propagate as errors. They must
        // not be converted into an authoritative offline-disable instruction.
        $state = $catalog->publicState();

        return [...$base, 'addon_active' => true, 'enabled' => (bool) ($state['enabled'] ?? false),
            'reason' => (string) ($state['reason'] ?? 'no_published_book'), 'books' => $state['books'] ?? []];
    }

    public function canPrepare(): bool
    {
        $catalog = $this->catalog();

        return $catalog !== null && $catalog->setupComplete();
    }

    public function adminStatus(): array
    {
        try {
            return $this->status();
        } catch (\RuntimeException) {
            return ['enabled' => false, 'addon_active' => $this->activeAddon() !== null, 'reason' => 'release_error'];
        }
    }
}
