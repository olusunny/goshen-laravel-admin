<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalNavigationAccessibilityTest extends TestCase
{
    public function test_mobile_navigation_prioritizes_wallet_and_preserves_access_to_more_portal_pages(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('grid-template-columns: repeat(5, minmax(0, 1fr));', $portal);
        $this->assertStringContainsString('data-nav-page="wallet" aria-controls="portalMain"', $portal);
        $this->assertStringContainsString('id="openBottomDrawer"', $portal);
        $this->assertStringContainsString('<span>More</span>', $portal);
        $this->assertStringContainsString("document.getElementById('openBottomDrawer')?.addEventListener", $portal);
    }

    public function test_portal_navigation_keeps_the_active_page_accessible_and_respects_reduced_motion(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('function updateNavigationState()', $portal);
        $this->assertStringContainsString("button.setAttribute('aria-current', 'page');", $portal);
        $this->assertStringContainsString('function focusActivePage()', $portal);
        $this->assertStringContainsString("window.matchMedia?.('(prefers-reduced-motion: reduce)').matches", $portal);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $portal);
        $this->assertStringContainsString('function setDrawerExpanded(expanded)', $portal);
        $this->assertStringContainsString('closeDrawer({ restoreFocus: true })', $portal);
    }
}
