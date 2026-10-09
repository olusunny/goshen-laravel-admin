<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminAccessReport;
use Illuminate\Console\Command;

class ExplainAdminAccess extends Command
{
    protected $signature = 'admin-permissions:explain {user : Web admin user ID}';

    protected $description = 'Report role/direct grants and menu hides without modifying access';

    public function handle(): int
    {
        $user = User::find($this->argument('user'));
        if (! $user) {
            $this->error('Web admin user not found.');

            return self::FAILURE;
        }
        $this->line(json_encode(AdminAccessReport::forUser($user), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
