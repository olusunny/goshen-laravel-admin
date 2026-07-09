<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('prayer-community:purge-expired --sync')->hourly();
Schedule::command('goshen:process-pending-payments --limit=100')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('goshen:process-wallet-topups --limit=50')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('goshen:process-experience-reminders --limit=100')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\AutomaticNotificationService::class)->processDue())->everyMinute();
Schedule::command('inbox:send-scheduled --limit=50')->everyMinute()->withoutOverlapping();
