<?php

namespace ChurchTools\DigitalCounseling\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckCounselingCommand extends Command
{
    protected $signature = 'counseling:health';

    protected $description = 'Check digital counseling package installation health.';

    public function handle(): int
    {
        $ok = true;

        try {
            foreach ($this->expectedTables() as $table) {
                if (! Schema::hasTable($table)) {
                    $this->error("Missing table: {$table}");
                    $ok = false;
                }
            }
        } catch (Throwable $exception) {
            $this->error('Database is not available for counseling health checks: '.$exception->getMessage());
            $ok = false;
        }

        $disk = (string) config('counseling.media.disk', 'local');
        try {
            Storage::disk($disk);
        } catch (Throwable $exception) {
            $this->error("Counseling media disk is not available: {$disk}");
            $ok = false;
        }

        if ((string) config('counseling.media.disk', 'local') === 'public') {
            $this->error('Counseling media disk must not be public.');
            $ok = false;
        }

        if ($ok) {
            $this->info('Digital counseling package health check passed.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @return array<int, string>
     */
    private function expectedTables(): array
    {
        return [
            'counseling_provider_profiles',
            'counseling_cases',
            'counseling_messages',
            'counseling_message_receipts',
            'counseling_assignments',
            'counseling_case_notes',
            'counseling_safeguarding_events',
            'counseling_case_events',
            'counseling_country_resources',
        ];
    }
}
