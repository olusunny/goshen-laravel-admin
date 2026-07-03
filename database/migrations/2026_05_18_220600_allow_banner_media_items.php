<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_items')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media_items MODIFY type VARCHAR(255) NOT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_items')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media_items MODIFY type ENUM('audio', 'video', 'music') NOT NULL");
        }
    }
};
