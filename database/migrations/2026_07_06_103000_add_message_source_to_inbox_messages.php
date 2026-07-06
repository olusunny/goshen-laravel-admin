<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inbox_messages') && ! Schema::hasColumn('inbox_messages', 'message_source')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                $table->string('message_source', 40)->default('admin')->after('legacy_id')->index();
            });
        }

        if (! Schema::hasTable('inbox_messages') || ! Schema::hasColumn('inbox_messages', 'message_source')) {
            return;
        }

        DB::table('inbox_messages')
            ->where(function ($query): void {
                $query->whereNull('message_source')
                    ->orWhere('message_source', '');
            })
            ->update(['message_source' => 'admin']);

        if (Schema::hasColumn('inbox_messages', 'schedule_type')) {
            DB::table('inbox_messages')
                ->where('schedule_type', 'generated')
                ->update(['message_source' => 'recurring_delivery']);
        }

        if (Schema::hasColumn('inbox_messages', 'scheduled_parent_id')) {
            DB::table('inbox_messages')
                ->whereNotNull('scheduled_parent_id')
                ->update(['message_source' => 'recurring_delivery']);
        }

        if (Schema::hasColumn('inbox_messages', 'notification_category')) {
            DB::table('inbox_messages')
                ->whereIn('notification_category', ['accommodation', 'testimonies'])
                ->where('message_source', 'admin')
                ->update(['message_source' => 'system']);
        }

        DB::table('inbox_messages')
            ->where('title', 'like', 'Welcome to MFM Triumphant Church%')
            ->where('message_source', 'admin')
            ->update(['message_source' => 'automatic']);

        DB::table('inbox_messages')
            ->whereIn('title', [
                'Goshen Retreat payment reminder',
                'Goshen Retreat registration cancelled',
                'Goshen wallet top-up needs attention',
                'Goshen wallet top-up failed',
                'Share your Goshen Experience',
                'Goshen referral points validated',
                'Wallet security reset approved',
            ])
            ->where('message_source', 'admin')
            ->update(['message_source' => 'system']);
    }

    public function down(): void
    {
        if (Schema::hasTable('inbox_messages') && Schema::hasColumn('inbox_messages', 'message_source')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                $table->dropColumn('message_source');
            });
        }
    }
};
