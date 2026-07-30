<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoshenTicketFamilyDetailsViewTest extends TestCase
{
    public function test_ticket_view_includes_a_ticket_scoped_family_summary_without_contact_details(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenTicketResource.php'));

        foreach ([
            "Section::make('Linked Goshen family')",
            "TextEntry::make('linked_family_name')",
            "TextEntry::make('linked_family_role')",
            "TextEntry::make('linked_family_members')",
            'private static function familyViewData(Ticket $ticket): ?array',
            "->with(['family.members.attendee.ticket'])",
            "GoshenAccommodationAllocation::query()",
            "static::familyAccommodationLabel(\$allocation)",
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }

        $start = strpos($source, "Section::make('Linked Goshen family')");
        $end = strpos("Section::make('Check-in history')", $start);
        $familySection = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString('email', strtolower($familySection));
        $this->assertStringNotContainsString('phone', strtolower($familySection));
    }
}
