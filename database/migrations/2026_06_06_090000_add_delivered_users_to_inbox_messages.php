<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inbox_messages', 'delivered_mobile_user_ids')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                $table->json('delivered_mobile_user_ids')->nullable()->after('selected_role_ids');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inbox_messages', 'delivered_mobile_user_ids')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                $table->dropColumn('delivered_mobile_user_ids');
            });
        }
    }
};
