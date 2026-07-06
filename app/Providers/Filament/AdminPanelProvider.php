<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AdminSummaryCards;
use App\Filament\Widgets\ActiveMobileUsersByCountryWidget;
use App\Filament\Widgets\DashboardOverview;
use App\Filament\Widgets\GoshenExperienceStatsWidget;
use App\Filament\Widgets\LocationInsightsWidget;
use App\Filament\Widgets\MediaMixChart;
use App\Filament\Widgets\TopContentWidget;
use App\Filament\Widgets\VisitorTrendChart;
use App\Filament\Widgets\WelcomeAccountWidget;
use App\Models\AppSetting;
use App\Services\Addons\AddonRuntimeLoader;
use App\Support\MediaUrl;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('MFM Triumphant Church Admin')
            ->brandLogo(fn () => $this->brandLogo())
            ->brandLogoHeight('3rem')
            ->favicon(asset('favicon.png'))
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups()
            ->navigationGroups($this->navigationGroups())
            ->colors([
                'primary' => Color::Amber,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.admin-shell')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.theme-switcher-topbar')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => view('filament.sidebar-menu-search')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => view('filament.sidebar-navigation-behavior')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources');

        foreach (app(AddonRuntimeLoader::class)->filamentResourceDiscoveries() as $discovery) {
            $panel->discoverResources(in: $discovery['path'], for: $discovery['namespace']);
        }

        if (is_dir(base_path('packages/sunny-fundraising/src/Filament/Resources'))) {
            $panel->discoverResources(
                in: base_path('packages/sunny-fundraising/src/Filament/Resources'),
                for: 'Sunny\\Fundraising\\Filament\\Resources',
            );
        }

        return $panel
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                WelcomeAccountWidget::class,
                AdminSummaryCards::class,
                DashboardOverview::class,
                ActiveMobileUsersByCountryWidget::class,
                GoshenExperienceStatsWidget::class,
                VisitorTrendChart::class,
                MediaMixChart::class,
                LocationInsightsWidget::class,
                TopContentWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function brandLogo(): HtmlString
    {
        $logo = MediaUrl::resolve(AppSetting::value('app_logo'));

        if ($logo) {
            return new HtmlString('<img src="'.e($logo).'" alt="MFM Triumphant Church Admin" class="com-admin-logo-image">');
        }

        return new HtmlString('<span class="com-admin-logo-mark" aria-label="MFM Triumphant Church Admin">MFM</span>');
    }

    /**
     * @return array<NavigationGroup>
     */
    private function navigationGroups(): array
    {
        return [
            NavigationGroup::make('Goshen Retreat')->collapsed(),
            NavigationGroup::make('Forms')->collapsed(),
            NavigationGroup::make('Giving')->collapsed(),
            NavigationGroup::make('Fundraising')->collapsed(),
            NavigationGroup::make('Messaging')->collapsed(),
            NavigationGroup::make('Community')->collapsed(),
            NavigationGroup::make('Content')->collapsed(),
            NavigationGroup::make('Media Library')->collapsed(),
            NavigationGroup::make('Programs')->collapsed(),
            NavigationGroup::make('Settings')->collapsed(),
            NavigationGroup::make('Legacy Accommodation Archive')->collapsed(),
        ];
    }
}
