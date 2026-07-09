<?php

namespace Sunny\Fundraising\Console;

use Illuminate\Console\Command;

class InstallFundraisingCommand extends Command
{
    protected $signature = 'fundraising:install {--force : Overwrite published package files}';

    protected $description = 'Install the fundraising package config, assets, and database tables.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        foreach (['fundraising-config', 'fundraising-assets', 'fundraising-views', 'fundraising-translations'] as $tag) {
            $this->call('vendor:publish', [
                '--tag' => $tag,
                '--force' => $force,
            ]);
        }

        $this->call('migrate', ['--force' => true]);
        $this->info('Fundraising package installed.');

        return self::SUCCESS;
    }
}
