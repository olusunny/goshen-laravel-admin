<?php

namespace ChurchTools\DigitalHymnBook\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class HymnPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Publish permission is deliberately not granted to existing roles.
        Permission::findOrCreate('manage_hymn', 'web');
        Permission::findOrCreate('publish_hymn', 'web');
    }
}
