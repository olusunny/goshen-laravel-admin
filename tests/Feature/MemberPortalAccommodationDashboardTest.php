<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalAccommodationDashboardTest extends TestCase
{
    public function test_dashboard_renders_the_member_room_allocation_from_existing_data(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $start = strpos($portal, 'function renderHome()');
        $end = strpos($portal, 'function walletAmount', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $dashboard = substr($portal, $start, $end - $start);

        $this->assertStringContainsString('id="homeAccommodation"', $portal);
        $this->assertLessThan(
            strpos($portal, 'id="homeNextAction"'),
            strpos($portal, 'id="homeAccommodation"')
        );
        $this->assertStringContainsString('const allocations = memberData.accommodation_allocations || [];', $dashboard);
        $this->assertStringContainsString('const hasRoomAllocationStatus = allocations.length > 0 || tickets.length > 0;', $dashboard);
        $this->assertStringContainsString('Room allocation', $dashboard);
        $this->assertStringContainsString("statusBadge('Room allocation pending')", $dashboard);
        $this->assertStringContainsString("allocation.attendee?.name || 'Registered attendee'", $dashboard);
        $this->assertStringContainsString("statusBadge(allocation.status || 'assigned')", $dashboard);
        $this->assertStringContainsString('accommodation-status-text', $dashboard);
        $darkAccommodationStyleStart = strpos($portal, 'html.theme-dark .accommodation-status-text');
        $darkAccommodationStyleEnd = strpos($portal, '}', $darkAccommodationStyleStart);

        $this->assertNotFalse($darkAccommodationStyleStart);
        $this->assertNotFalse($darkAccommodationStyleEnd);

        $darkAccommodationStyles = substr(
            $portal,
            $darkAccommodationStyleStart,
            $darkAccommodationStyleEnd - $darkAccommodationStyleStart
        );

        $this->assertStringContainsString('color: #fff;', $darkAccommodationStyles);
        $this->assertStringContainsString('font-weight: 700;', $darkAccommodationStyles);
        $this->assertStringNotContainsString('allocation.check_in_note', $dashboard);
        $this->assertStringContainsString('Your retreat details', $dashboard);
        $this->assertStringNotContainsString('Registration ready', $dashboard);
    }
}
