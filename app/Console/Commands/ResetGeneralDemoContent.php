<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetGeneralDemoContent extends Command
{
    private const CONFIRMATION_TOKEN = 'RESET-GENERAL-DEMO-CONTENT';

    /**
     * @var array<int, array{label: string, table: string}>
     */
    private const COUNT_TARGETS = [
        ['label' => 'On-demand forms', 'table' => 'dynamic_forms'],
        ['label' => 'On-demand form fields', 'table' => 'dynamic_form_fields'],
        ['label' => 'Form submissions', 'table' => 'dynamic_form_submissions'],
        ['label' => 'Donations', 'table' => 'donations'],
        ['label' => 'Fundraising campaigns', 'table' => 'fundraising_campaigns'],
        ['label' => 'Fundraising campaign media', 'table' => 'fundraising_campaign_media'],
        ['label' => 'Fundraising contributions', 'table' => 'fundraising_campaign_contributions'],
        ['label' => 'Contact recipients', 'table' => 'contact_recipients'],
        ['label' => 'Contact messages', 'table' => 'contact_messages'],
        ['label' => 'Email notifications', 'table' => 'email_notifications'],
        ['label' => 'Prayer points', 'table' => 'prayer_points'],
    ];

    /**
     * @var array<int, string>
     */
    private const DELETE_ORDER = [
        'dynamic_form_submissions',
        'dynamic_form_fields',
        'dynamic_forms',
        'fundraising_campaign_contributions',
        'fundraising_campaign_media',
        'fundraising_campaigns',
        'contact_messages',
        'contact_recipients',
        'email_notifications',
        'donations',
        'prayer_points',
    ];

    protected $signature = 'app:reset-general-demo-content
        {--confirm= : Type RESET-GENERAL-DEMO-CONTENT to perform the reset}
        {--dry-run : Print affected record counts without changing data}';

    protected $description = 'Reset demo forms, donations, fundraising, contact, email notification, and prayer point content.';

    public function handle(): int
    {
        if ($this->option('confirm') !== self::CONFIRMATION_TOKEN) {
            $this->error('Confirmation token required; no data was changed.');

            return self::FAILURE;
        }

        $this->line('Before reset:');
        $this->writeCounts($this->counts());

        if ((bool) $this->option('dry-run')) {
            $this->info('Dry run only; no data was changed.');

            return self::SUCCESS;
        }

        $this->dropFinancialWriteGuards();

        try {
            DB::transaction(function (): void {
                foreach (self::DELETE_ORDER as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }
            });
        } finally {
            $this->installFinancialWriteGuards();
        }

        $this->info('Reset complete.');
        $this->line('After reset:');
        $this->writeCounts($this->counts());

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach (self::COUNT_TARGETS as $target) {
            $counts[$target['label']] = $this->tableCount($target['table']);
        }

        return $counts;
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function writeCounts(array $counts): void
    {
        $this->table(
            ['Metric', 'Count'],
            collect($counts)
                ->map(fn (int $count, string $metric): array => [$metric, $count])
                ->values()
                ->all(),
        );
    }

    private function dropFinancialWriteGuards(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_update');
        DB::unprepared('DROP TRIGGER IF EXISTS donations_prevent_completed_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_update');
        DB::unprepared('DROP TRIGGER IF EXISTS fundraising_contributions_prevent_succeeded_delete');
    }

    private function installFinancialWriteGuards(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->installSqliteFinancialWriteGuards();

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->installMysqlFinancialWriteGuards();
        }
    }

    private function installSqliteFinancialWriteGuards(): void
    {
        if (Schema::hasTable('donations')) {
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

    private function installMysqlFinancialWriteGuards(): void
    {
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
}
