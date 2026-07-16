<?php

namespace ChurchTools\DigitalCounseling\Console;

use Illuminate\Console\Command;

class UpdateCounselingCommand extends Command
{
    use InstallsCounselingPermissions;

    protected $signature = 'counseling:update';

    protected $description = 'Update the digital counseling package database tables and permissions.';

    public function handle(): int
    {
        $this->installCounselingPermissions();
        $this->call('migrate', ['--force' => true]);
        $this->info('Digital counseling package updated.');

        return self::SUCCESS;
    }
}
