<?php

namespace Tests\Unit;

use App\Filament\Resources\GoshenExperienceSurveyResource;
use App\Filament\Widgets\ActiveMobileUsersByCountryWidget;
use App\Filament\Widgets\AdminSummaryCards;
use App\Filament\Widgets\DashboardOverview;
use App\Filament\Widgets\GoshenBookingStatsWidget;
use App\Filament\Widgets\GoshenExperienceStatsWidget;
use App\Filament\Widgets\LocationInsightsWidget;
use App\Filament\Widgets\MediaMixChart;
use App\Filament\Widgets\TopContentWidget;
use App\Filament\Widgets\VisitorTrendChart;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\AdminPermissions;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Mockery;
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

    public function test_dashboard_widgets_have_an_explicit_operational_order_and_access_gate(): void
    {
        $widgets = [
            DashboardOverview::class => -90,
            GoshenBookingStatsWidget::class => -80,
            ActiveMobileUsersByCountryWidget::class => -70,
            GoshenExperienceStatsWidget::class => -60,
            VisitorTrendChart::class => -50,
            LocationInsightsWidget::class => -40,
            AdminSummaryCards::class => -30,
            MediaMixChart::class => -20,
            TopContentWidget::class => -10,
        ];

        foreach ($widgets as $widget => $sort) {
            $reflection = new ReflectionClass($widget);
            $property = $reflection->getProperty('sort');
            $property->setAccessible(true);

            $this->assertSame($sort, $property->getValue());
            $this->assertFalse($widget::canView());
        }
    }

    public function test_experience_widget_requires_access_to_surveys_and_responses(): void
    {
        $surveyPermission = AdminPermissions::resourcePermission(GoshenExperienceSurveyResource::class);
        $user = Mockery::mock();
        $user->shouldReceive('hasRole')->with('super_admin')->twice()->andReturnFalse();
        $user->shouldReceive('can')->twice()->andReturnUsing(
            fn (string $permission): bool => $permission === $surveyPermission,
        );

        Auth::shouldReceive('user')->twice()->andReturn($user);

        $this->assertFalse(GoshenExperienceStatsWidget::canView());
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
