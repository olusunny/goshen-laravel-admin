<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'goshen_ticket_pdf_template'],
            [
                'group' => 'goshen_retreat',
                'value' => 'executive_white',
                'is_secret' => false,
                'description' => 'Preferred PDF ticket template for Goshen Retreat tickets.',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->where('key', 'goshen_ticket_pdf_template')->delete();
    }
};
