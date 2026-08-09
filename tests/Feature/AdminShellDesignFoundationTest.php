<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminShellDesignFoundationTest extends TestCase
{
    public function test_admin_shell_provides_opt_in_responsive_accessibility_foundations(): void
    {
        $shell = file_get_contents(resource_path('views/filament/admin-shell.blade.php'));

        $this->assertStringContainsString('--goshen-admin-space-4:', $shell);
        $this->assertStringContainsString('.goshen-admin-section', $shell);
        $this->assertStringContainsString('.goshen-admin-state', $shell);
        $this->assertStringContainsString('.goshen-admin-table-wrap', $shell);
        $this->assertStringContainsString('.fi-ta-ctn', $shell);
        $this->assertStringContainsString('.fi-ta-empty-state', $shell);
        $this->assertStringContainsString('.fi-fo-field-wrp-label', $shell);
        $this->assertStringContainsString(':focus-visible', $shell);
        $this->assertStringContainsString('@media (pointer: coarse)', $shell);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $shell);
        $this->assertStringNotContainsString('transition: all', $shell);
    }

    public function test_admin_login_uses_the_supplied_responsive_accessible_background(): void
    {
        $shell = file_get_contents(resource_path('views/filament/admin-shell.blade.php'));
        $asset = public_path('images/filament-admin-login-background.svg');

        $this->assertFileExists($asset);
        $this->assertSame(
            '4fe6d8bb8d5f988620015011f42fee73d629444a913998be40ec24368e5ff687',
            hash_file('sha256', $asset),
        );
        $this->assertStringContainsString('viewBox="0 0 1920 1080"', file_get_contents($asset));

        $matched = preg_match(
            "/@if \\(request\\(\\)->routeIs\\('filament\\.admin\\.auth\\.login'\\)\\)(.*?)@endif/s",
            $shell,
            $matches,
        );

        $this->assertSame(1, $matched, 'Login background styles should be scoped to the Filament login route.');

        $loginStyles = $matches[1];

        $this->assertStringContainsString("url('{{ asset('images/filament-admin-login-background.svg') }}')", $loginStyles);
        $this->assertStringContainsString('background-position: center;', $loginStyles);
        $this->assertStringContainsString('background-repeat: no-repeat;', $loginStyles);
        $this->assertStringContainsString('background-size: cover;', $loginStyles);
        $this->assertStringContainsString('.fi-simple-layout::before', $loginStyles);
        $this->assertStringContainsString('padding: clamp(', $loginStyles);
        $this->assertStringContainsString('.fi-simple-main :where(', $loginStyles);
        $this->assertStringContainsString(':focus-visible', $loginStyles);
        $this->assertStringContainsString('@media (forced-colors: active)', $loginStyles);
        $this->assertStringNotContainsString(
            'filament-admin-login-background.svg',
            str_replace($matches[0], '', $shell),
            'The background must not leak onto authenticated admin pages.',
        );
    }

    public function test_high_volume_tables_use_filament_mobile_stack_layout(): void
    {
        foreach ([
            'MobileUserResource.php',
            'GoshenBookingResource.php',
            'GoshenTicketResource.php',
            'GoshenWalletResource.php',
            'GoshenWalletWithdrawalRequestResource.php',
            'GoshenWalletLedgerEntryResource.php',
            'GoshenWalletSavingsPlanResource.php',
            'InboxMessageResource.php',
            'AccommodationPaymentResource.php',
        ] as $resource) {
            $source = file_get_contents(app_path('Filament/Resources/'.$resource));

            $this->assertStringContainsString('->stackedOnMobile()', $source, $resource.' should use Filament\'s mobile table layout.');
        }
    }

    public function test_sidebar_search_keeps_a_visible_focus_indicator_and_announces_results(): void
    {
        $shell = file_get_contents(resource_path('views/filament/admin-shell.blade.php'));
        $search = file_get_contents(resource_path('views/filament/sidebar-menu-search.blade.php'));
        $behavior = file_get_contents(resource_path('views/filament/sidebar-navigation-behavior.blade.php'));

        $this->assertStringContainsString('.goshen-sidebar-search:focus-within', $shell);
        $this->assertStringContainsString('data-goshen-menu-search-status', $search);
        $this->assertStringContainsString('updateSearchStatus', $behavior);
    }

    public function test_light_admin_shell_uses_a_distinct_accessible_sidebar_and_topbar_palette(): void
    {
        $shell = file_get_contents(resource_path('views/filament/admin-shell.blade.php'));

        $this->assertStringContainsString('--goshen-admin-sidebar: #143c33;', $shell);
        $this->assertStringContainsString('--goshen-admin-topbar: #fffaf0;', $shell);
        $this->assertStringContainsString('--goshen-admin-sidebar-active: #f6b91a;', $shell);
        $this->assertStringContainsString('html:not(.dark) .fi-topbar', $shell);
        $this->assertStringContainsString('html:not(.dark) .fi-sidebar .fi-sidebar-header-ctn', $shell);
        $this->assertStringContainsString('.fi-sidebar-item-btn:focus-visible', $shell);
        $this->assertStringContainsString('background: var(--goshen-admin-sidebar-hover) !important;', $shell);
        $this->assertStringContainsString('@media (min-width: 1024px) and (pointer: coarse)', $shell);
        $this->assertStringContainsString('@media (forced-colors: active)', $shell);
        $this->assertStringContainsString('.dark {', $shell);
        $this->assertStringContainsString('--goshen-admin-sidebar: #20342e;', $shell);
        $this->assertStringNotContainsString('html:not(.dark) .fi-topbar > nav', $shell);
        $this->assertStringNotContainsString('linear-gradient', $shell);

        $this->assertGreaterThan(
            strpos($shell, '@media (min-width: 1024px) {'),
            strpos($shell, '@media (min-width: 1024px) and (pointer: coarse) {'),
            'The coarse-pointer override must come after the compact desktop navigation rules.',
        );
    }

    public function test_attendee_summary_uses_reflowable_shared_classes(): void
    {
        $booking = file_get_contents(app_path('Filament/Resources/GoshenBookingResource.php'));
        $shell = file_get_contents(resource_path('views/filament/admin-shell.blade.php'));

        $this->assertStringContainsString('goshen-admin-attendee-details', $booking);
        $this->assertStringContainsString('.goshen-admin-attendee-details', $shell);
    }

    public function test_custom_payment_fields_have_programmatic_labels(): void
    {
        $page = file_get_contents(resource_path('views/filament/pages/payment-gateways.blade.php'));

        $this->assertStringContainsString('for="test-publishable-key"', $page);
        $this->assertStringContainsString('id="test-publishable-key"', $page);
        $this->assertStringContainsString('for="live-publishable-key"', $page);
        $this->assertStringContainsString('id="live-publishable-key"', $page);
    }

    public function test_custom_dashboard_widgets_use_the_shared_operational_design_system(): void
    {
        $shell = file_get_contents(resource_path('views/filament/admin-shell.blade.php'));

        foreach ([
            'active-mobile-users-by-country.blade.php',
            'goshen-experience-stats.blade.php',
            'location-insights.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path('views/filament/widgets/'.$view));

            $this->assertStringContainsString('goshen-dashboard-grid', $source, $view.' should use the shared dashboard layout.');
            $this->assertStringContainsString('goshen-dashboard-empty', $source, $view.' should use the shared empty state.');
            $this->assertStringNotContainsString('<style>', $source, $view.' should not carry widget-specific style overrides.');
            $this->assertStringNotContainsString('linear-gradient', $source, $view.' should not use decorative gradients.');
        }

        $this->assertStringContainsString('.goshen-dashboard-grid', $shell);
        $this->assertStringContainsString('.goshen-dashboard-empty', $shell);
        $this->assertStringContainsString('.goshen-dashboard-progress', $shell);
    }

    public function test_location_dashboard_queries_total_visits_once_per_render(): void
    {
        $source = file_get_contents(resource_path('views/filament/widgets/location-insights.blade.php'));

        $this->assertSame(1, substr_count($source, 'getTotalVisits()'));
    }

    public function test_custom_admin_controls_expose_focus_and_state_to_assistive_technology(): void
    {
        $payment = file_get_contents(resource_path('views/filament/pages/payment-gateways.blade.php'));
        $menu = file_get_contents(resource_path('views/filament/pages/admin-menu-settings.blade.php'));
        $settings = file_get_contents(resource_path('views/filament/pages/app-settings.blade.php'));
        $backups = file_get_contents(resource_path('views/filament/pages/cloud-backups.blade.php'));
        $sidebar = file_get_contents(resource_path('views/filament/sidebar-navigation-behavior.blade.php'));

        $this->assertStringContainsString('.pg-mode input:focus-visible + .pg-mode-card', $payment);
        $this->assertStringContainsString('aria-label="Visible for', $menu);
        $this->assertStringContainsString(':aria-pressed=', $settings);
        $this->assertStringContainsString('role="progressbar"', $backups);
        $this->assertStringContainsString('aria-valuenow=', $backups);
        $this->assertStringContainsString('safeStorage', $sidebar);
    }

    public function test_ticket_email_filter_matches_the_latest_email_status_shown_in_the_table(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenTicketResource.php'));

        $this->assertStringContainsString("whereHas('latestEmailLog'", $source);
        $this->assertStringContainsString("whereDoesntHave('latestEmailLog'", $source);
        $this->assertStringNotContainsString("whereHas('emailLogs'", $source);
    }
}
