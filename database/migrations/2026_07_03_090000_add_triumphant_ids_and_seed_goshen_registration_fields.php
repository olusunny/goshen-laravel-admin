<?php

use App\Services\GoshenRegistrationFieldService;
use App\Services\TriumphantIdService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Personal\EventInstallments\Models\Event;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('mobile_users', 'triumphant_id_sequence')) {
                $table->unsignedInteger('triumphant_id_sequence')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('mobile_users', 'triumphant_id')) {
                $table->string('triumphant_id', 20)->nullable()->unique()->after('triumphant_id_sequence');
            }
        });

        app(TriumphantIdService::class)->ensureRoles();

        if (Schema::hasTable('ei_events') && Schema::hasTable('ei_event_attendee_fields')) {
            $fields = app(GoshenRegistrationFieldService::class);

            Event::query()
                ->where(function ($query): void {
                    $query
                        ->where('settings->module', 'goshen_retreat')
                        ->orWhere('settings->module', 'goshen-retreat')
                        ->orWhere('settings->app_module', 'goshen_retreat')
                        ->orWhere('slug', 'like', 'goshen-retreat%')
                        ->orWhere('slug', 'like', 'goshen-%')
                        ->orWhere('name', 'like', '%Goshen Retreat%');
                })
                ->orderBy('id')
                ->each(fn (Event $event) => $fields->ensureDefaultsForEvent($event, true));
        }

        $this->backfillTriumphantIds();
    }

    public function down(): void
    {
        Schema::table('mobile_users', function (Blueprint $table): void {
            if (Schema::hasColumn('mobile_users', 'triumphant_id')) {
                $table->dropUnique(['triumphant_id']);
                $table->dropColumn('triumphant_id');
            }

            if (Schema::hasColumn('mobile_users', 'triumphant_id_sequence')) {
                $table->dropUnique(['triumphant_id_sequence']);
                $table->dropColumn('triumphant_id_sequence');
            }
        });
    }

    private function backfillTriumphantIds(): void
    {
        if (! Schema::hasTable('mobile_users')) {
            return;
        }

        $service = app(TriumphantIdService::class);

        DB::table('mobile_users')
            ->where('is_deleted', false)
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($id) use ($service): void {
                $user = \App\Models\MobileUser::query()->find($id);

                if ($user) {
                    $service->assignFor($user);
                }
            });
    }
};
