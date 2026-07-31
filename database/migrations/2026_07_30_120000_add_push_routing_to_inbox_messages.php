<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table): void {
            $table->string('push_action', 80)->nullable()->after('send_push');
            $table->json('push_data')->nullable()->after('push_action');
            $table->string('push_visibility', 10)->default('PUBLIC')->after('push_data');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table): void {
            $table->dropColumn(['push_action', 'push_data', 'push_visibility']);
        });
    }
};
