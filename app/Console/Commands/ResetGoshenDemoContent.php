<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetGoshenDemoContent extends Command
{
    private const CONFIRMATION_TOKEN = 'RESET-GOSHEN-DEMO-CONTENT';

    /**
     * @var array<int, array{label: string, table: string}>
     */
    private const COUNT_TARGETS = [
        ['label' => 'Goshen accommodation allocations', 'table' => 'goshen_accommodation_allocations'],
        ['label' => 'Goshen tickets', 'table' => 'ei_tickets'],
        ['label' => 'Wallet auto top-up plans', 'table' => 'goshen_wallet_savings_plans'],
        ['label' => 'Goshen bookings', 'table' => 'ei_bookings'],
        ['label' => 'Goshen experience responses', 'table' => 'goshen_experience_responses'],
        ['label' => 'Goshen experience surveys', 'table' => 'goshen_experience_surveys'],
        ['label' => 'Quiz attempts', 'table' => 'goshen_quiz_attempts'],
        ['label' => 'Goshen quizzes', 'table' => 'goshen_quizzes'],
        ['label' => 'Goshen retreat editions', 'table' => 'ei_events'],
        ['label' => 'Goshen schedule sessions', 'table' => 'ei_event_schedules'],
        ['label' => 'Wallet withdrawal requests', 'table' => 'goshen_wallet_withdrawal_requests'],
        ['label' => 'Wallet activities', 'table' => 'goshen_wallet_ledger_entries'],
        ['label' => 'Goshen vouchers', 'table' => 'goshen_vouchers'],
        ['label' => 'Goshen voucher usages', 'table' => 'goshen_voucher_usages'],
        ['label' => 'Goshen referral point entries', 'table' => 'goshen_referral_point_entries'],
        ['label' => 'Goshen wallet goals', 'table' => 'goshen_wallet_goals'],
    ];

    /**
     * Delete order is child-to-parent so foreign keys remain useful guardrails.
     *
     * @var array<int, string>
     */
    private const DELETE_ORDER = [
        'goshen_accommodation_allocations',
        'goshen_experience_reminders',
        'goshen_experience_responses',
        'goshen_experience_questions',
        'goshen_experience_surveys',
        'goshen_quiz_winners',
        'goshen_quiz_attempts',
        'goshen_quiz_questions',
        'goshen_quizzes',
        'goshen_quiz_celebration_media',
        'goshen_referral_point_entries',
        'goshen_voucher_usages',
        'goshen_vouchers',
        'goshen_wallet_withdrawal_requests',
        'goshen_wallet_savings_plans',
        'goshen_wallet_goals',
        'goshen_wallet_ledger_entries',
        'ei_event_audit_logs',
        'ei_ticket_email_logs',
        'ei_ticket_documents',
        'ei_ticket_check_ins',
        'ei_tickets',
        'ei_attendees',
        'ei_booking_lines',
        'ei_payment_transactions',
        'ei_payment_installments',
        'ei_bookings',
        'ei_payment_plans',
        'ei_event_attendee_fields',
        'ei_event_ticket_types',
        'ei_event_schedules',
        'ei_events',
        'ei_payment_gateway_webhook_events',
    ];

    protected $signature = 'goshen:reset-demo-content
        {--confirm= : Type RESET-GOSHEN-DEMO-CONTENT to perform the reset}
        {--dry-run : Print affected record counts without changing data}';

    protected $description = 'Reset demo Goshen retreat, booking, ticket, quiz, survey, voucher, and wallet activity records.';

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

        DB::transaction(function (): void {
            foreach (self::DELETE_ORDER as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            if (Schema::hasTable('goshen_wallets')) {
                DB::table('goshen_wallets')->update([
                    'balance' => 0,
                    'goal_amount' => null,
                    'goal_label' => null,
                    'goal_target_at' => null,
                    'updated_at' => now(),
                ]);
            }
        });

        $this->info('Reset complete.');
        $this->line('After reset:');
        $this->writeCounts($this->counts());

        return self::SUCCESS;
    }

    /**
     * @return array<string, int|string>
     */
    private function counts(): array
    {
        $counts = [];

        foreach (self::COUNT_TARGETS as $target) {
            $counts[$target['label']] = $this->tableCount($target['table']);
        }

        $counts['Wallets'] = $this->tableCount('goshen_wallets');
        $counts['Wallet balance total'] = $this->walletBalanceTotal();

        return $counts;
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    private function walletBalanceTotal(): string
    {
        if (! Schema::hasTable('goshen_wallets')) {
            return '0.00';
        }

        return number_format((float) DB::table('goshen_wallets')->sum('balance'), 2, '.', '');
    }

    /**
     * @param  array<string, int|string>  $counts
     */
    private function writeCounts(array $counts): void
    {
        $this->table(
            ['Metric', 'Count'],
            collect($counts)
                ->map(fn (int|string $count, string $metric): array => [$metric, $count])
                ->values()
                ->all(),
        );
    }
}
