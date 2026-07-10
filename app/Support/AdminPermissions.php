<?php

namespace App\Support;

use App\Services\Addons\AddonRuntimeLoader;
use Illuminate\Support\Str;

class AdminPermissions
{
    public const CLOUD_BACKUPS = 'manage_cloud_backups';

    public const FUNDRAISING_VIEW = 'fundraising.view';

    public const FUNDRAISING_MANAGE = 'fundraising.manage';

    public const FUNDRAISING_CONTRIBUTE = 'fundraising.contribute';

    public const FUNDRAISING_MEDIA_MANAGE = 'fundraising.media.manage';

    public const GOSHEN_TICKET_ISSUE = 'goshen_ticket.issue';

    public const PAYMENT_GATEWAYS = 'manage_payment_gateways';

    public const WALLET_SECURITY_RESETS = 'manage_wallet_security_resets';

    public static function resourcePermission(string $resourceClass): string
    {
        return 'manage_' . Str::of(class_basename($resourceClass))
            ->beforeLast('Resource')
            ->snake()
            ->toString();
    }

    public static function resourceLabel(string $resourceClass): string
    {
        if (method_exists($resourceClass, 'getNavigationLabel')) {
            $label = $resourceClass::getNavigationLabel();
            if (filled($label)) {
                return $label;
            }
        }

        return Str::of(class_basename($resourceClass))
            ->beforeLast('Resource')
            ->headline()
            ->toString();
    }

    public static function resourceGroup(string $resourceClass): string
    {
        if (method_exists($resourceClass, 'getNavigationGroup')) {
            $group = $resourceClass::getNavigationGroup();
            if (filled($group)) {
                return (string) $group;
            }
        }

        return 'General';
    }

    public static function resources(): array
    {
        $resources = [];

        foreach (self::resourcePaths() as $basePath => $namespace) {
            foreach (glob($basePath.'/*Resource.php') ?: [] as $path) {
                $class = $namespace . '\\' . basename($path, '.php');
                if (! class_exists($class)) {
                    continue;
                }

                $resources[$class] = [
                    'permission' => self::resourcePermission($class),
                    'label' => self::resourceLabel($class),
                    'group' => self::resourceGroup($class),
                ];
            }
        }

        uasort($resources, fn ($a, $b) => [$a['group'], $a['label']] <=> [$b['group'], $b['label']]);

        return $resources;
    }

    public static function all(): array
    {
        return collect(self::resources())
            ->mapWithKeys(fn ($meta) => [$meta['permission'] => "{$meta['group']} - {$meta['label']}"])
            ->put(self::CLOUD_BACKUPS, 'Settings - Cloud Backups')
            ->put(self::FUNDRAISING_VIEW, 'Fundraising - View campaigns')
            ->put(self::FUNDRAISING_MANAGE, 'Fundraising - Manage campaigns')
            ->put(self::FUNDRAISING_CONTRIBUTE, 'Fundraising - Contribute')
            ->put(self::FUNDRAISING_MEDIA_MANAGE, 'Fundraising - Manage media')
            ->put(self::GOSHEN_TICKET_ISSUE, 'Goshen Retreat - Issue tickets')
            ->put(self::PAYMENT_GATEWAYS, 'Settings - Payment Gateways')
            ->put(self::WALLET_SECURITY_RESETS, 'Goshen Retreat - Wallet Security Resets')
            ->all();
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }

    private static function resourcePaths(): array
    {
        $paths = [
            app_path('Filament/Resources') => 'App\\Filament\\Resources',
        ];

        $fundraisingPath = base_path('packages/sunny-fundraising/src/Filament/Resources');
        if (is_dir($fundraisingPath)) {
            $paths[$fundraisingPath] = 'Sunny\\Fundraising\\Filament\\Resources';
        }

        if (app()->bound(AddonRuntimeLoader::class)) {
            foreach (app(AddonRuntimeLoader::class)->filamentResourceDiscoveries() as $discovery) {
                $paths[$discovery['path']] = $discovery['namespace'];
            }
        }

        return $paths;
    }
}
