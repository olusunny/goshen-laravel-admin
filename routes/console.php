<?php

use App\Services\Addons\AddonRuntimeLoader;
use App\Services\AutomaticNotificationService;
use App\Services\CronJobMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('addons:refresh-runtime-cache', function () {
    app(AddonRuntimeLoader::class)->refreshActiveAddonCache();
    $this->info('Active add-on runtime cache refreshed.');
})->purpose('Refresh active add-on paths and runtime discovery cache');

$cronMonitor = app(CronJobMonitor::class);

Schedule::call(fn () => app(CronJobMonitor::class)->markSchedulerHeartbeat())
    ->name('cron-monitor:laravel-scheduler-heartbeat')
    ->everyMinute();

$cronMonitor->watch(Schedule::command('prayer-community:purge-expired --sync')->hourly(), 'prayer_purge_expired');
$cronMonitor->watch(Schedule::command('goshen:process-pending-payments --limit=100')->everyFifteenMinutes()->withoutOverlapping(), 'goshen_pending_payments');
$cronMonitor->watch(Schedule::command('goshen:process-wallet-topups --limit=50')->everyFifteenMinutes()->withoutOverlapping(), 'goshen_wallet_topups');
$cronMonitor->watch(Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(), 'database_queue');
$cronMonitor->watch(Schedule::command('goshen:reconcile-refund-pending --limit=100')->everyFiveMinutes()->withoutOverlapping(), 'goshen_refund_reconciliation');
$cronMonitor->watch(Schedule::command('goshen:process-experience-reminders --limit=100')->everyFifteenMinutes()->withoutOverlapping(), 'goshen_experience_reminders');
$cronMonitor->watch(Schedule::call(fn () => app(AutomaticNotificationService::class)->processDue())->everyMinute(), 'automatic_notifications');
$cronMonitor->watch(Schedule::command('inbox:send-scheduled --limit=50')->everyMinute()->withoutOverlapping(), 'scheduled_inbox_messages');
