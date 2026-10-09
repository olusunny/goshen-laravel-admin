<?php

namespace App\Support;

use App\Filament\Pages\AppSettings;
use App\Filament\Pages\GoshenRetreatConsole;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;
use Throwable;
use UnitEnum;

class AdminMenuRegistry
{
    /**
     * @return array<int, array{key: string, type: string, class: string, label: string, group: string, sort: int|null}>
     */
    public static function items(): array
    {
        $items = [];

        foreach (AdminPermissions::resources() as $class => $meta) {
            if (! self::resourceShouldAppearInMatrix($class)) {
                continue;
            }

            $items[] = [
                'key' => self::resourceKey($class),
                'type' => 'resource',
                'class' => $class,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'sort' => method_exists($class, 'getNavigationSort') ? $class::getNavigationSort() : null,
            ];
        }

        foreach (self::pageClasses() as $class) {
            $items[] = [
                'key' => self::pageKey($class),
                'type' => 'page',
                'class' => $class,
                'label' => method_exists($class, 'getNavigationLabel') ? $class::getNavigationLabel() : class_basename($class),
                'group' => self::normalizeNavigationValue(method_exists($class, 'getNavigationGroup') ? $class::getNavigationGroup() : null) ?: 'General',
                'sort' => method_exists($class, 'getNavigationSort') ? $class::getNavigationSort() : null,
            ];
        }

        usort($items, fn (array $a, array $b): int => [
            $a['group'],
            $a['sort'] ?? 9999,
            $a['label'],
        ] <=> [
            $b['group'],
            $b['sort'] ?? 9999,
            $b['label'],
        ]);

        return $items;
    }

    public static function resourceKey(string $resourceClass): string
    {
        return 'resource:'.$resourceClass;
    }

    public static function pageKey(string $pageClass): string
    {
        return 'page:'.$pageClass;
    }

    public static function visibleForResource(string $resourceClass): bool
    {
        return self::visibleForCurrentUser(self::resourceKey($resourceClass));
    }

    public static function resourceIsConfigurable(string $resourceClass): bool
    {
        return self::resourceShouldAppearInMatrix($resourceClass);
    }

    public static function visibleForPage(string $pageClass): bool
    {
        return self::visibleForCurrentUser(self::pageKey($pageClass));
    }

    public static function visibleForCurrentUser(string $menuKey): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return self::visibleForUser($user, $menuKey);
    }

    public static function visibleForUser(User $user, string $menuKey): bool
    {
        // Legacy visibility rows remain for rollback; permissions decide navigation.
        return true;
    }

    /**
     * @return array<int, array{type: string, class: class-string, label: string, description: string}>
     */
    public static function settingsDestinations(): array
    {
        return [
            ['type' => 'page', 'class' => \App\Filament\Pages\PaymentGateways::class,
                'label' => 'Payment Gateways', 'description' => 'Stripe test/live keys, webhooks, and checkout URLs.'],
            ['type' => 'page', 'class' => \App\Filament\Pages\GoogleFirebaseSettings::class,
                'label' => 'Google & Firebase', 'description' => 'Google login IDs, fingerprints, and Firebase Admin status.'],
            ['type' => 'page', 'class' => \App\Filament\Pages\GoshenReferralSettings::class,
                'label' => 'Referral Settings', 'description' => 'Referral points, wallet conversion rate, and conversion minimums.'],
            ['type' => 'page', 'class' => \App\Filament\Pages\GoshenTicketPdfTemplates::class,
                'label' => 'Ticket PDF Templates', 'description' => 'Choose the preferred Goshen ticket PDF design and preview options.'],
            ['type' => 'page', 'class' => \App\Filament\Pages\CloudBackups::class,
                'label' => 'Cloud Backups', 'description' => 'Google Drive and OneDrive backup providers.'],
            ['type' => 'page', 'class' => \App\Filament\Pages\CronJobs::class,
                'label' => 'Cron Jobs', 'description' => 'Scheduler health report and cPanel cron setup commands.'],
            ['type' => 'resource', 'class' => \App\Filament\Resources\AiProviderSettingResource::class,
                'label' => 'AI Providers', 'description' => 'AI provider model, API key, and test configuration.'],
            ['type' => 'resource', 'class' => \App\Filament\Resources\AddonResource::class,
                'label' => 'Add-ons', 'description' => 'Installed add-ons, lifecycle status, and package health.'],
            ['type' => 'resource', 'class' => \App\Filament\Resources\RoleResource::class,
                'label' => 'Role Permissions', 'description' => 'Admin roles and feature permissions.'],
            ['type' => 'resource', 'class' => \App\Filament\Resources\AppSettingResource::class,
                'label' => 'System Settings Maintenance', 'description' => 'Super Admin configuration and secret replacement.'],
        ];
    }

    public static function settingsQuickLinks(): array
    {
        return collect(self::settingsDestinations())->filter(function (array $item): bool {
            $class = $item['class'];

            return $item['type'] === 'page'
                ? $class::canAccess() && self::visibleForPage($class)
                : $class::canViewAny() && self::visibleForResource($class);
        })->map(fn (array $item): array => [
            'label' => $item['label'],
            'description' => $item['description'],
            'url' => $item['class']::getUrl(),
        ])->values()->all();
    }

    private static function pageClasses(): array
    {
        return array_values(array_unique(array_merge(
            [AppSettings::class, GoshenRetreatConsole::class],
            collect(self::settingsDestinations())->where('type', 'page')->pluck('class')->all(),
        )));
    }

    private static function resourceShouldAppearInMatrix(string $resourceClass): bool
    {
        try {
            $reflection = new ReflectionClass($resourceClass);

            $navigationMethod = $reflection->getMethod('shouldRegisterNavigation');
            $authorizationMethod = new \ReflectionMethod(AuthorizesResourceAccess::class, 'shouldRegisterNavigation');

            if (
                in_array(AuthorizesResourceAccess::class, class_uses_recursive($resourceClass), true)
                && $navigationMethod->getFileName() === $authorizationMethod->getFileName()
                && $navigationMethod->getStartLine() === $authorizationMethod->getStartLine()
            ) {
                return true;
            }

            if ($navigationMethod->getDeclaringClass()->getName() === $resourceClass) {
                return false;
            }

            if ($reflection->hasProperty('shouldRegisterNavigation')) {
                $property = $reflection->getProperty('shouldRegisterNavigation');
                $property->setAccessible(true);

                return (bool) $property->getValue();
            }

            return true;
        } catch (Throwable) {
            return true;
        }
    }

    private static function normalizeNavigationValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return filled($value) ? (string) $value : null;
    }
}
