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

        $this->deleteDemoRows();

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

    private function deleteDemoRows(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                foreach (self::DELETE_ORDER as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->truncate();
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            return;
        }

        DB::transaction(function (): void {
            foreach (self::DELETE_ORDER as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        });
    }
}
