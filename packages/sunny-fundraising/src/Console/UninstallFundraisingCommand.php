<?php

namespace Sunny\Fundraising\Console;

use Illuminate\Console\Command;

class UninstallFundraisingCommand extends Command
{
    protected $signature = 'fundraising:uninstall {--keep-data : Preserve fundraising database tables}';

    protected $description = 'Show safe uninstall guidance for the fundraising package.';

    public function handle(): int
    {
        if ($this->option('keep-data')) {
            $this->info('Fundraising package files can be deactivated or removed by the host add-on manager. Data will be preserved.');

            return self::SUCCESS;
        }

        $this->warn('For safety, this command does not drop contribution data automatically.');
        $this->line('Use the host add-on manager to deactivate or uninstall the package while preserving records.');

        return self::SUCCESS;
    }
}
