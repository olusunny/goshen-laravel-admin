<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPortalAttendeeProfilePrefillTest extends TestCase
{
    public function test_account_holder_profile_values_prefill_matching_retreat_fields_with_common_aliases(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('function accountHolderAttendeeProfileFields(attendeeFields = [])', $portal);
        $this->assertStringContainsString('function canonicalAttendeeFieldValues(fields = {})', $portal);
        $this->assertStringContainsString('const normalizedValue = `${value}`.trim();', $portal);
        $this->assertStringContainsString("if (normalizedValue === '') return values;", $portal);
        $this->assertStringContainsString('const formKeys = new Set((attendeeFields || []).map((field) => attendeeFieldIdentity(field?.key)).filter(Boolean));', $portal);
        $this->assertStringContainsString('return Object.fromEntries(Object.entries(profileFields).filter(([key]) => formKeys.has(key)));', $portal);

        foreach ([
            "sex: 'gender'",
            "profile_title: 'title'",
            "salutation: 'title'",
            "marital: 'marital_status'",
            "membership_status: 'member_type'",
            "membership_type: 'member_type'",
            "country: 'country_of_residence'",
            "residence_country: 'country_of_residence'",
            "countryofresidence: 'country_of_residence'",
            "state: 'state_county_province'",
            "county: 'state_county_province'",
            "province: 'state_county_province'",
            "statecountyprovince: 'state_county_province'",
            "home_address: 'address'",
        ] as $alias) {
            $this->assertStringContainsString($alias, $portal);
        }

        foreach ([
            'gender: user.gender,',
            'title: user.title ?? user.profile_title,',
            'marital_status: user.marital_status,',
            'member_type: user.member_type,',
            'country_of_residence: user.country_of_residence,',
            'state_county_province: user.state_county_province,',
            'address: user.address,',
        ] as $profileField) {
            $this->assertStringContainsString($profileField, $portal);
        }
    }

    public function test_saved_draft_wins_and_profile_prefill_is_limited_to_the_account_holder(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $start = strpos($portal, 'function renderAttendeeFields(quantity, event, existingAttendees = [], ticket = null)');
        $end = strpos($portal, 'function optionValue(option)', $start ?: 0);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $renderAttendees = substr($portal, $start, $end - $start);
        $this->assertStringContainsString('const attendeeFields = index === 0', $renderAttendees);
        $this->assertStringContainsString('{ ...accountHolderAttendeeProfileFields(fields), ...canonicalAttendeeFieldValues(existing), ...canonicalAttendeeFieldValues(existing.custom_fields) }', $renderAttendees);
        $this->assertStringContainsString(': { ...canonicalAttendeeFieldValues(existing), ...canonicalAttendeeFieldValues(existing.custom_fields) };', $renderAttendees);
        $this->assertStringContainsString('renderRegistrationField(field, index, attendeeFields, currency)', $renderAttendees);
        $this->assertStringContainsString('const normalizedValue = `${value}`.trim();', $portal);
        $this->assertStringContainsString("if (normalizedValue === '') return values;", $portal);
        $this->assertStringNotContainsString('age_group: user.', $portal);
        $this->assertStringNotContainsString('free_church_bus_interest: user.', $portal);
        $this->assertStringNotContainsString('volunteer_department: user.', $portal);
    }

    public function test_profile_values_are_limited_to_existing_non_birthday_attendee_fields(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));
        $aliasStart = strpos($portal, 'const aliases = {');
        $aliasEnd = strpos($portal, 'return aliases[normalized] || normalized;', $aliasStart ?: 0);

        $this->assertNotFalse($aliasStart);
        $this->assertNotFalse($aliasEnd);
        $aliases = substr($portal, $aliasStart, $aliasEnd - $aliasStart);

        $this->assertStringContainsString('formKeys.has(key)', $portal);
        $this->assertStringNotContainsString('fields.push(', $portal);
        $this->assertStringNotContainsString('attendeeFields.push(', $portal);
        $this->assertStringNotContainsString("birthday: 'birthday_month_day'", $aliases);
        $this->assertStringNotContainsString("date_of_birth: 'birthday_month_day'", $aliases);
        $this->assertStringNotContainsString('birthday_month_day: user.', $portal);
    }

    public function test_dynamic_controls_keep_accessible_labels_without_adding_new_fields(): void
    {
        $portal = file_get_contents(resource_path('views/member/portal.blade.php'));

        $this->assertStringContainsString('function attendeeFieldControlId(attendeeIndex, key)', $portal);
        $this->assertStringContainsString('<label for="${escapeHtml(controlId)}">${label}</label><select id="${escapeHtml(controlId)}"', $portal);
        $this->assertStringContainsString('<label for="${escapeHtml(controlId)}">${label}</label><textarea id="${escapeHtml(controlId)}"', $portal);
        $this->assertStringContainsString('role="radiogroup" aria-labelledby="${escapeHtml(labelId)}"', $portal);
        $this->assertStringContainsString('<label class="choice" for="${escapeHtml(optionId)}">', $portal);
    }
}
