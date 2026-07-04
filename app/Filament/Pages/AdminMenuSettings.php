<?php

namespace App\Filament\Pages;

use App\Models\AdminMenuRoleVisibility;
use App\Support\AdminMenuRegistry;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use UnitEnum;

class AdminMenuSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Admin Menu Settings';

    protected static ?string $title = 'Admin Menu Settings';

    protected static ?string $slug = 'admin-menu-settings';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.admin-menu-settings';

    /**
     * @var array<int, array{id: int, name: string}>
     */
    public array $roles = [];

    /**
     * @var array<int, array{hash: string, key: string, label: string, group: string, type: string}>
     */
    public array $items = [];

    /**
     * @var array<int|string, array<string, bool>>
     */
    public array $visibility = [];

    /**
     * @var array<string, string>
     */
    private array $menuKeysByHash = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasRole('super_admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->roles = Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'super_admin')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
            ])
            ->values()
            ->all();

        $this->items = collect(AdminMenuRegistry::items())
            ->map(function (array $item): array {
                $hash = sha1($item['key']);
                $this->menuKeysByHash[$hash] = $item['key'];

                return [
                    'hash' => $hash,
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'group' => $item['group'],
                    'type' => $item['type'],
                ];
            })
            ->values()
            ->all();

        $stored = AdminMenuRoleVisibility::query()
            ->whereIn('role_id', collect($this->roles)->pluck('id')->all())
            ->get()
            ->mapWithKeys(fn (AdminMenuRoleVisibility $row): array => [
                $row->role_id.'|'.$row->menu_key => (bool) $row->is_visible,
            ]);

        foreach ($this->roles as $role) {
            foreach ($this->items as $item) {
                $this->visibility[$role['id']][$item['hash']] = $stored->get(
                    $role['id'].'|'.$item['key'],
                    true,
                );
            }
        }
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $roleIds = collect($this->roles)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $keysByHash = collect($this->items)->mapWithKeys(
            fn (array $item): array => [$item['hash'] => $item['key']],
        );

        foreach ($roleIds as $roleId) {
            foreach ($keysByHash as $hash => $menuKey) {
                AdminMenuRoleVisibility::query()->updateOrCreate(
                    [
                        'role_id' => $roleId,
                        'menu_key' => $menuKey,
                    ],
                    [
                        'is_visible' => (bool) ($this->visibility[$roleId][$hash] ?? false),
                    ],
                );
            }
        }

        Notification::make()
            ->title('Admin menu settings saved')
            ->body('Role-based admin navigation visibility has been updated.')
            ->success()
            ->send();

        $this->mount();
    }
}
