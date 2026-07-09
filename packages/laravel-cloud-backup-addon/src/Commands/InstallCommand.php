<?php

namespace ChurchTools\CloudBackup\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'cloud-backup:install';

    protected $description = 'Publish Cloud Backup config and migrations.';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'cloud-backup-config']);
        $this->call('vendor:publish', ['--tag' => 'cloud-backup-migrations']);

        $this->info('Cloud Backup addon files published. Run php artisan migrate next.');

        return self::SUCCESS;
    }
}
