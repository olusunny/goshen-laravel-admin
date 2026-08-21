<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenRetreatMaterialResource;
use App\Services\Addons\AddonRuntimeLoader;
use App\Services\TriumphantIdService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncAdminPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_missing_catalog_permissions_without_granting_them_to_roles(): void
    {
        app(TriumphantIdService::class)->ensureRoles();
        $permissionName = AdminPermissions::resourcePermission(
            GoshenRetreatMaterialResource::class,
        );
        Permission::query()
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->delete();

        $this->assertDatabaseMissing('permissions', [
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);

        $this->artisan('admin-permissions:sync')->assertSuccessful();

        $permission = Permission::query()
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->firstOrFail();
        $role = Role::query()
            ->where('name', TriumphantIdService::IT_MANAGER_ROLE)
            ->where('guard_name', 'web')
            ->firstOrFail();

        $this->assertFalse($role->hasPermissionTo($permission));
    }

    public function test_addon_permission_catalog_includes_top_level_and_capability_permissions(): void
    {
        $labels = app(AddonRuntimeLoader::class)->permissionLabelsForManifest([
            'name' => 'Test add-on',
            'permissions' => ['test.manage'],
            'capabilities' => [
                'test' => ['permissions' => ['test.view', 'test.manage']],
            ],
        ]);

        $this->assertSame([
            'test.manage' => 'Test add-on - Manage',
            'test.view' => 'Test add-on - View',
        ], $labels);
    }
}
