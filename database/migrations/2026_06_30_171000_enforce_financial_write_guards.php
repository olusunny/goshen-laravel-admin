<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->installSqliteGuards();

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        try {
            $this->installMysqlGuards();
        } catch (QueryException $exception) {
            $this->dropMysqlGuards();

            if ((int) ($exception->errorInfo[1] ?? 0) === 1419 && app()->environment(['local', 'testing'])) {
                report($exception);

                return;
            }

            throw $exception;
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_update');
        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_update');
        DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_delete');
    }

    private function installSqliteGuards(): void
    {
        if (Schema::hasTable('donations')) {
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
        }

        if (Schema::hasTable('fundraising_campaign_contributions')) {
            DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_update');
            DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_delete');

            DB::unprepared("
                CREATE TRIGGER fundraising_contributions_prevent_succeeded_update
                BEFORE UPDATE ON fundraising_campaign_contributions
                FOR EACH ROW
                WHEN LOWER(COALESCE(OLD.status, '')) = 'succeeded' OR OLD.succeeded_at IS NOT NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Succeeded fundraising contributions are locked and cannot be edited or deleted.');
                END
            ");

            DB::unprepared("
                CREATE TRIGGER fundraising_contributions_prevent_succeeded_delete
                BEFORE DELETE ON fundraising_campaign_contributions
                FOR EACH ROW
                WHEN LOWER(COALESCE(OLD.status, '')) = 'succeeded' OR OLD.succeeded_at IS NOT NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Succeeded fundraising contributions are locked and cannot be edited or deleted.');
                END
            ");
        }
    }

    private function installMysqlGuards(): void
    {
        $this->dropMysqlGuards();

        if (Schema::hasTable('donations')) {
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
        }

        if (Schema::hasTable('fundraising_campaign_contributions')) {
            DB::unprepared("
                CREATE TRIGGER fundraising_contributions_prevent_succeeded_update
                BEFORE UPDATE ON fundraising_campaign_contributions
                FOR EACH ROW
                BEGIN
                    IF LOWER(COALESCE(OLD.status, '')) = 'succeeded' OR OLD.succeeded_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Succeeded fundraising contributions are locked and cannot be edited or deleted.';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER fundraising_contributions_prevent_succeeded_delete
                BEFORE DELETE ON fundraising_campaign_contributions
                FOR EACH ROW
                BEGIN
                    IF LOWER(COALESCE(OLD.status, '')) = 'succeeded' OR OLD.succeeded_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Succeeded fundraising contributions are locked and cannot be edited or deleted.';
                    END IF;
                END
            ");
        }
    }

    private function dropMysqlGuards(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_update');
        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_update');
        DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_delete');
    }
};
