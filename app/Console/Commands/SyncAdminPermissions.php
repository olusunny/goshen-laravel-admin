<?php

namespace App\Console\Commands;

use App\Support\AdminPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncAdminPermissions extends Command
{
    protected $signature = 'admin-permissions:sync';

    protected $description = 'Create missing web admin permissions without changing role assignments';

    public function handle(PermissionRegistrar $registrar): int
    {
        $created = DB::transaction(function (): int {
            $created = 0;

            foreach (AdminPermissions::names() as $name) {
                $permission = Permission::query()->firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);

                if ($permission->wasRecentlyCreated) {
                    $created++;
                }
            }

            return $created;
        });

        $registrar->forgetCachedPermissions();
        $this->components->info("Admin permission catalog synchronized ({$created} created).");

        return self::SUCCESS;
    }
}
