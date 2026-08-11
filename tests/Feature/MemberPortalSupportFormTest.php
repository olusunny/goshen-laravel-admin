<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalSupportFormTest extends TestCase
{
    public function test_member_portal_support_page_renders_and_submits_a_support_request(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $start = strpos($portal, 'function renderSupport()');
        $end = strpos($portal, 'async function startCheckout', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $supportRenderer = substr($portal, $start, $end - $start);

        $this->assertStringContainsString('data-support-form', $supportRenderer);
        $this->assertStringContainsString('Send a support request', $supportRenderer);
        $this->assertStringContainsString('name="subject" required', $supportRenderer);
        $this->assertStringContainsString('name="reference" maxlength="120"', $supportRenderer);
        $this->assertStringContainsString('name="message" minlength="10" maxlength="5000" required', $supportRenderer);
        $this->assertStringContainsString('data-support-feedback', $supportRenderer);
        $this->assertStringContainsString('mailto:', $supportRenderer);
        $this->assertStringContainsString('tel:', $supportRenderer);
        $this->assertStringContainsString('const phone = eventInquiryPhone(event);', $supportRenderer);

        $this->assertStringContainsString("event.target.closest('[data-support-form]')", $portal);
        $this->assertStringContainsString("apiPost('/api/submit_contact', requestData)", $portal);
        $this->assertStringContainsString('supportRequest.reportValidity()', $portal);
        $this->assertStringContainsString("supportRequest.setAttribute('aria-busy', 'true')", $portal);
        $this->assertStringContainsString("submitButton.textContent = 'Sending...'", $portal);
        $this->assertStringContainsString("feedback.setAttribute('role', 'alert')", $portal);
        $this->assertStringContainsString("notify(error.message, 'error')", $portal);
    }
}
