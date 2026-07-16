<?php

namespace ChurchTools\DigitalCounseling\Console;

use Illuminate\Console\Command;

class InstallCounselingCommand extends Command
{
    use InstallsCounselingPermissions;

    protected $signature = 'counseling:install {--force : Overwrite published package files}';

    protected $description = 'Install the digital counseling package config and database tables.';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'counseling-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->installCounselingPermissions();
        $this->call('migrate', ['--force' => true]);
        $this->info('Digital counseling package installed. It remains disabled until COUNSELING_ENABLED is true and the addon is active.');

        return self::SUCCESS;
    }
}
