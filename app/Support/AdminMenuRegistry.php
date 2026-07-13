<?php

namespace App\Support;

use App\Filament\Pages\CloudBackups;
use App\Filament\Pages\AppSettings;
use App\Filament\Pages\CronJobs;
use App\Filament\Pages\GoshenReferralSettings;
use App\Filament\Pages\GoshenRetreatConsole;
use App\Filament\Pages\GoogleFirebaseSettings;
use App\Filament\Pages\PaymentGateways;
use App\Models\AdminMenuRoleVisibility;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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
        if ($user->hasRole('super_admin')) {
            return true;
        }

        try {
            if (! Schema::hasTable('admin_menu_role_visibilities')) {
                return true;
            }

            $roleIds = $user->roles()
                ->where('guard_name', 'web')
                ->pluck('roles.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($roleIds === []) {
                return true;
            }

            $visibilityByRole = AdminMenuRoleVisibility::query()
                ->where('menu_key', $menuKey)
                ->whereIn('role_id', $roleIds)
                ->pluck('is_visible', 'role_id');

            foreach ($roleIds as $roleId) {
                if (! $visibilityByRole->has($roleId) || (bool) $visibilityByRole->get($roleId)) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @return array<int, class-string>
     */
    private static function pageClasses(): array
    {
        return [
            AppSettings::class,
            CloudBackups::class,
            CronJobs::class,
            GoshenReferralSettings::class,
            GoshenRetreatConsole::class,
            GoogleFirebaseSettings::class,
            PaymentGateways::class,
        ];
    }

    private static function resourceShouldAppearInMatrix(string $resourceClass): bool
    {
        try {
            $reflection = new ReflectionClass($resourceClass);

            if (! $reflection->hasProperty('shouldRegisterNavigation')) {
                return true;
            }

            $property = $reflection->getProperty('shouldRegisterNavigation');
            $property->setAccessible(true);

            return (bool) $property->getValue();
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
