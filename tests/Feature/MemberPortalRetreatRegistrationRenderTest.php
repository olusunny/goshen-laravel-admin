<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalRetreatRegistrationRenderTest extends TestCase
{
    public function test_retreat_registration_form_uses_the_selected_ticket_for_quantity_controls(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $start = strpos($portal, 'function renderRegistrationForm(event)');
        $end = strpos($portal, 'function renderRegistrationProfileNotice()', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $registrationForm = substr($portal, $start, $end - $start);

        $this->assertStringContainsString('renderQuantityStepper(selectedTicket, initialQuantity, quantityLabelId)', $registrationForm);
        $this->assertStringContainsString('ticketQuantityHint(selectedTicket)', $registrationForm);
        $this->assertStringNotContainsString('firstTicket', $registrationForm);
    }
}
