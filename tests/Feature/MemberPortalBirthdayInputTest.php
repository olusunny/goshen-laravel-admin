<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalBirthdayInputTest extends TestCase
{
    public function test_profile_birthday_input_has_live_mm_dd_formatting_and_calendar_validation(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('name="birthday_month_day"', $portal);
        $this->assertStringContainsString('inputmode="numeric"', $portal);
        $this->assertStringContainsString('maxlength="5"', $portal);
        $this->assertStringContainsString('normalizeBirthdayMonthDayInput', $portal);
        $this->assertStringContainsString('birthdayMonthDayError', $portal);
        $this->assertStringContainsString('validateBirthdayMonthDayInput(birthdayInput, { format: true })', $portal);
    }
}
