<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalRetreatRegistrationRenderTest extends TestCase
{
    public function test_retreat_registration_form_switches_family_tickets_to_role_based_details(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $start = strpos($portal, 'function renderRegistrationForm(event)');
        $end = strpos($portal, 'function renderRegistrationProfileNotice()', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $registrationForm = substr($portal, $start, $end - $start);

        $this->assertStringContainsString('const familyTicket = isFamilyTicket(selectedTicket);', $registrationForm);
        $this->assertStringContainsString('renderFamilyRegistrationFields(draft?.family || {})', $registrationForm);
        $this->assertStringContainsString('familyTicket ? 1 : initialQuantity', $registrationForm);
        $this->assertStringContainsString('ticketQuantityHint(selectedTicket)', $registrationForm);
        $this->assertStringNotContainsString('firstTicket', $registrationForm);
    }
}
