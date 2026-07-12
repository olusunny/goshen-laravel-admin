<?php

namespace App\Filament\Pages;

use App\Services\CronJobMonitor;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CronJobs extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Cron Jobs';

    protected static ?string $title = 'Cron Jobs';

    protected static ?string $slug = 'cron-jobs';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.cron-jobs';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && AdminMenuRegistry::visibleForPage(static::class);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole('super_admin')
            || $user->can(AdminPermissions::CRON_MONITOR)
        );
    }

    public function mount(CronJobMonitor $monitor): void
    {
        $summary = $monitor->report()['summary'];

        if (($summary['failed'] ?? 0) > 0) {
            Notification::make()
                ->title('Cron job failure detected')
                ->body('One or more scheduled jobs last reported a failed run. Review the Cron Jobs page for details.')
                ->danger()
                ->send();

            return;
        }

        if (($summary['warning'] ?? 0) > 0) {
            Notification::make()
                ->title('Cron jobs need attention')
                ->body('Some scheduled jobs have not reported a recent successful run yet.')
                ->warning()
                ->send();
        }
    }

    public function getViewData(): array
    {
        return app(CronJobMonitor::class)->report();
    }
}
