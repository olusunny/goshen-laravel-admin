<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_users')
            || ! Schema::hasColumn('mobile_users', 'member_type')
            || ! Schema::hasColumn('mobile_users', 'triumphant_id')
            || ! Schema::hasColumn('mobile_users', 'triumphant_id_sequence')) {
            return;
        }

        DB::table('mobile_users')
            ->whereRaw('LOWER(TRIM(COALESCE(member_type, \'\'))) = ?', ['visitor'])
            ->where(function ($query): void {
                $query->whereNotNull('triumphant_id')
                    ->orWhereNotNull('triumphant_id_sequence');
            })
            ->update([
                'triumphant_id' => null,
                'triumphant_id_sequence' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Released IDs are deliberately not restored for visitor accounts.
    }
};
