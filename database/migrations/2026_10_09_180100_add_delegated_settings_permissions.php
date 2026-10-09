<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing access while allowing each settings page to be delegated separately.
        $mapping = [
            'manage_google_firebase' => ['manage_app_setting'],
            'manage_referral_settings' => ['manage_app_setting', 'manage_goshen_referral_point_entry'],
            'manage_ticket_pdf_settings' => ['manage_goshen_ticket', 'goshen_ticket.issue'],
        ];
        DB::transaction(function () use ($mapping): void {
            foreach ($mapping as $name => $previous) {
                $permission = Permission::findOrCreate($name, 'web');
                $ids = Permission::where('guard_name', 'web')->whereIn('name', $previous)->pluck('id');
                foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
                    foreach (DB::table($table)->whereIn('permission_id', $ids)->get() as $assignment) {
                        $row = (array) $assignment;
                        $row['permission_id'] = $permission->id;
                        DB::table($table)->insertOrIgnore($row);
                    }
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain grants on rollback: an operator may have delegated them since migration.
    }
};
