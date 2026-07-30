<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoshenBookingFamilyDetailsViewTest extends TestCase
{
    public function test_booking_view_resolves_direct_and_historically_linked_families(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenBookingResource.php'));

        $this->assertStringContainsString("Section::make('Linked Goshen family')", $source);
        $this->assertStringContainsString("->orWhereHas('members.attendee'", $source);
        $this->assertStringContainsString("->with(['members.attendee.ticket'])", $source);
        $this->assertStringContainsString("TextEntry::make('family_members')", $source);
    }
}
