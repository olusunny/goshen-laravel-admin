<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalImunifyErrorTest extends TestCase
{
    public function test_portal_replaces_the_imunify_bot_protection_message_with_member_safe_guidance(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('function messageFromErrorPayload(payload, fallback)', $portal);
        $this->assertStringContainsString("/access denied by imunify360 bot-protection/i.test(message)", $portal);
        $this->assertStringContainsString("return 'We could not complete that request because the server security check blocked it. Please try again shortly. If it continues, contact support.';", $portal);
        $this->assertStringContainsString("return payload?.message || payload?.msg || Object.values(payload?.errors || {})?.flat?.()?.[0] || fallback;", $portal);
    }
}
