<?php

namespace ChurchTools\DigitalCounseling\Console;

use Illuminate\Console\Command;

class UninstallCounselingCommand extends Command
{
    protected $signature = 'counseling:uninstall {--purge : Drop counseling tables. Requires explicit confirmation.}';

    protected $description = 'Disable or optionally purge the digital counseling package.';

    public function handle(): int
    {
        if (! $this->option('purge')) {
            $this->info('Digital counseling package left in place. Disable it with COUNSELING_ENABLED=false or deactivate the addon record.');

            return self::SUCCESS;
        }

        if (! $this->confirm('This will permanently drop counseling tables. Continue?')) {
            $this->info('Purge cancelled.');

            return self::SUCCESS;
        }

        $this->call('migrate:rollback', [
            '--path' => 'packages/digital-counseling-addon/database/migrations',
            '--force' => true,
        ]);

        $this->info('Digital counseling package tables purged.');

        return self::SUCCESS;
    }
}
