<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalProfileCompletionFlowTest extends TestCase
{
    public function test_profile_completion_flow_keeps_member_session_state_private_and_opt_in(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString("const profileCompletionNoticeKeyPrefix = 'goshen_profile_completion_notice_2026_07_v2';", $portal);
        $this->assertStringContainsString("const pendingRegistrationKeyPrefix = 'goshen_pending_registration_v2';", $portal);
        $this->assertStringContainsString('function memberSessionStorageKey(prefix, user = currentUser)', $portal);
        $this->assertStringContainsString('const memberId = `${user?.id || \'\'}`.trim();', $portal);
        $this->assertStringContainsString('function clearMemberPortalSessionState(user = currentUser)', $portal);
        $this->assertStringContainsString('clearMemberPortalSessionState(currentUser);', $portal);
        $this->assertStringContainsString('beginProfileCompletion(document.querySelector(\'.registration-form\'));', $portal);
        $this->assertStringContainsString('Your profile is ready. We restored your retreat registration so you can finish payment.', $portal);
        $this->assertStringContainsString("if (event.key !== 'Escape') return;", $portal);

        $noticeStart = strpos($portal, 'function maybeShowProfileCompletionNotice()');
        $noticeEnd = strpos($portal, 'function handlePaymentReturnNotice()', $noticeStart ?: 0);
        $this->assertNotFalse($noticeStart);
        $this->assertNotFalse($noticeEnd);

        $notice = substr($portal, $noticeStart, $noticeEnd - $noticeStart);
        $this->assertStringNotContainsString('beginProfileCompletion();', $notice);
        $this->assertStringNotContainsString('1800', $notice);
    }

    public function test_profile_completion_notice_requires_the_member_to_continue_to_profile(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('let profileCompletionNoticePreviousFocus = null;', $portal);
        $this->assertStringContainsString('function profileCompletionNoticeFocusableElements()', $portal);
        $this->assertStringContainsString('function setProfileCompletionNoticeBackgroundInert(isInert)', $portal);
        $this->assertStringContainsString('portalShell.inert = isInert;', $portal);
        $this->assertStringContainsString("portalShell.setAttribute('aria-hidden', 'true');", $portal);
        $this->assertStringContainsString("portalShell.removeAttribute('aria-hidden');", $portal);
        $this->assertStringContainsString('profileCompletionNoticePreviousFocus = document.activeElement instanceof HTMLElement', $portal);
        $this->assertStringContainsString('setProfileCompletionNoticeBackgroundInert(true);', $portal);
        $this->assertStringContainsString('setProfileCompletionNoticeBackgroundInert(false);', $portal);
        $this->assertStringContainsString("event.key === 'Tab'", $portal);
        $this->assertStringContainsString('profileCompletionNotice.contains(document.activeElement)', $portal);
        $this->assertStringContainsString('data-profile-completion-action="complete"', $portal);
        $this->assertStringNotContainsString('I will do this later', $portal);
        $this->assertStringContainsString('event.preventDefault();', $portal);
        $this->assertStringContainsString("profileCompletionNotice.querySelector('[data-profile-completion-action=\"complete\"]')?.focus();", $portal);
        $this->assertStringContainsString('name="birthday_month_day"', $portal);
        $this->assertStringContainsString('aria-describedby="profileBirthdayMonthDayHint" required', $portal);
    }

    public function test_existing_booking_payments_recover_without_overwriting_a_registration_draft(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('function beginBookingPaymentProfileRecovery()', $portal);
        $this->assertStringContainsString('beginProfileCompletion(null, {', $portal);
        $this->assertStringContainsString("returnPage: 'payments',", $portal);
        $this->assertStringContainsString('Your pending payment is safe, and we will take you back to it when you are done.', $portal);
        $this->assertStringContainsString('if (returnPage === \'payments\' && !profileNeedsCompletion())', $portal);
        $this->assertStringContainsString("showPage('payments');", $portal);
        $this->assertStringContainsString('Your profile is ready. Your pending payment is waiting for you on the Payments page.', $portal);

        foreach ([
            ['function startCheckout(bookingId, paymentId)', 'function walletFormPayload(form)'],
            ['async function walletPay(bookingId, button)', 'async function downloadAuthenticatedDocument(url, filename)'],
            ['const voucher = event.target.closest(\'.voucher-pay-form\');', 'document.getElementById(\'portalMain\').addEventListener(\'click\''],
        ] as [$startMarker, $endMarker]) {
            $start = strpos($portal, $startMarker);
            $end = strpos($portal, $endMarker, $start ?: 0);

            $this->assertNotFalse($start);
            $this->assertNotFalse($end);
            $this->assertStringContainsString('beginBookingPaymentProfileRecovery();', substr($portal, $start, $end - $start));
        }
    }
}
