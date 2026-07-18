<?php

namespace Tests\Unit;

use App\Filament\Widgets\DashboardOverview;
use App\Filament\Widgets\MediaMixChart;
use App\Filament\Widgets\VisitorTrendChart;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use ReflectionClass;
use Tests\TestCase;

class AdminBackgroundPollingTest extends TestCase
{
    public function test_admin_database_notifications_do_not_poll_in_background(): void
    {
        $panel = (new AdminPanelProvider($this->app))->panel(Panel::make());

        $this->assertNull($panel->getDatabaseNotificationsPollingInterval());
    }

    public function test_dashboard_widgets_do_not_poll_in_background(): void
    {
        foreach ([DashboardOverview::class, MediaMixChart::class, VisitorTrendChart::class] as $widget) {
            $this->assertNull($this->getPollingInterval($widget));
        }
    }

    /**
     * @param  class-string  $widget
     */
    private function getPollingInterval(string $widget): ?string
    {
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('pollingInterval');
        $property->setAccessible(true);

        return $property->getValue(app($widget));
    }
}
