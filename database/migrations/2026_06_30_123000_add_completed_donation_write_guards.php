<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_update');
            DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_delete');

            DB::unprepared("
                CREATE TRIGGER donations_prevent_completed_update
                BEFORE UPDATE ON donations
                FOR EACH ROW
                WHEN LOWER(COALESCE(OLD.status, '')) IN ('paid', 'success', 'completed') OR OLD.paid_at IS NOT NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Completed giving records are locked and cannot be edited or deleted.');
                END
            ");

            DB::unprepared("
                CREATE TRIGGER donations_prevent_completed_delete
                BEFORE DELETE ON donations
                FOR EACH ROW
                WHEN LOWER(COALESCE(OLD.status, '')) IN ('paid', 'success', 'completed') OR OLD.paid_at IS NOT NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Completed giving records are locked and cannot be edited or deleted.');
                END
            ");

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        try {
            DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_update');
            DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_delete');

            DB::unprepared("
                CREATE TRIGGER donations_prevent_completed_update
                BEFORE UPDATE ON donations
                FOR EACH ROW
                BEGIN
                    IF LOWER(COALESCE(OLD.status, '')) IN ('paid', 'success', 'completed') OR OLD.paid_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Completed giving records are locked and cannot be edited or deleted.';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER donations_prevent_completed_delete
                BEFORE DELETE ON donations
                FOR EACH ROW
                BEGIN
                    IF LOWER(COALESCE(OLD.status, '')) IN ('paid', 'success', 'completed') OR OLD.paid_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Completed giving records are locked and cannot be edited or deleted.';
                    END IF;
                END
            ");
        } catch (QueryException $exception) {
            DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_update');
            DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_delete');

            if ((int) ($exception->errorInfo[1] ?? 0) !== 1419) {
                throw $exception;
            }

            if (! app()->environment(['local', 'testing'])) {
                throw $exception;
            }

            Log::warning(
                'Donation database write guards were not created because MySQL binary logging requires SUPER privilege or log_bin_trust_function_creators=1.',
                ['migration' => __FILE__],
            );
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_update');
        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_delete');
    }
};
