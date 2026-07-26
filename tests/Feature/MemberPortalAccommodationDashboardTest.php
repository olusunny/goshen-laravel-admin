<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalAccommodationDashboardTest extends TestCase
{
    public function test_dashboard_renders_the_member_room_allocation_from_existing_data(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $start = strpos($portal, 'function renderHome()');
        $end = strpos('function walletAmount', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $dashboard = substr($portal, $start, $end - $start);

        $this->assertStringContainsString('id="homeAccommodation"', $portal);
        $this->assertStringContainsString('const allocations = memberData.accommodation_allocations || [];', $dashboard);
        $this->assertStringContainsString('Room allocation', $dashboard);
        $this->assertStringContainsString("allocation.attendee?.name || 'Registered attendee'", $dashboard);
        $this->assertStringContainsString("statusBadge(allocation.status || 'assigned')", $dashboard);
        $this->assertStringNotContainsString('allocation.check_in_note', $dashboard);
    }
}
