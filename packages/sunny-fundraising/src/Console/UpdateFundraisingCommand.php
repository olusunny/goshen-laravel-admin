<?php

namespace Sunny\Fundraising\Console;

use Illuminate\Console\Command;

class UpdateFundraisingCommand extends Command
{
    protected $signature = 'fundraising:update {--force : Overwrite published package files}';

    protected $description = 'Update fundraising package assets and run pending migrations.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        foreach (['fundraising-assets', 'fundraising-views', 'fundraising-translations'] as $tag) {
            $this->call('vendor:publish', [
                '--tag' => $tag,
                '--force' => $force,
            ]);
        }

        $this->call('migrate', ['--force' => true]);
        $this->info('Fundraising package updated.');

        return self::SUCCESS;
    }
}
