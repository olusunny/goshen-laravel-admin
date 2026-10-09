<?php

use App\Support\AppSettingsSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $hub = Permission::findOrCreate(AppSettingsSections::HUB_PERMISSION, 'web');
            foreach (array_keys(AppSettingsSections::sections()) as $section) {
                Permission::findOrCreate(AppSettingsSections::permission($section), 'web');
            }
            $legacy = Permission::where('name', 'manage_app_setting')->where('guard_name', 'web')->value('id');
            // A legacy hub grant does not establish intent to delegate sensitive sections.
            foreach (['role_has_permissions' => 'role', 'model_has_permissions' => 'user'] as $table => $type) {
                foreach (DB::table($table)->where('permission_id', $legacy)->get() as $assignment) {
                    $row = (array) $assignment;
                    $row['permission_id'] = $hub->id;
                    if (DB::table($table)->insertOrIgnore($row)) {
                        DB::table('admin_access_audits')->insert([
                            'actor_id' => null, 'target_type' => $type, 'target_id' => $row['role_id'] ?? $row['model_id'],
                            'action' => 'settings_hub_migrated', 'before' => json_encode(['legacy_permission' => 'manage_app_setting']),
                            'after' => json_encode(['added_permission' => AppSettingsSections::HUB_PERMISSION, 'section_grants' => []]),
                            'created_at' => now(),
                        ]);
                    }
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain assignments that administrators may have subsequently configured.
    }
};
