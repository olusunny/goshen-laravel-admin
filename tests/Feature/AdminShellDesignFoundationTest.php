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

    public function test_high_volume_tables_use_filament_mobile_stack_layout(): void
    {
        foreach ([
            'MobileUserResource.php',
            'GoshenBookingResource.php',
            'GoshenTicketResource.php',
            'GoshenWalletResource.php',
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
}
