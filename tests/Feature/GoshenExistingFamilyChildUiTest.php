<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenTicketResource;
use Tests\TestCase;

class GoshenExistingFamilyChildUiTest extends TestCase
{
    public function test_add_child_action_uses_the_existing_family_service_contract(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenTicketResource.php'));

        $this->assertStringContainsString("Actions\\Action::make('addChildToExistingFamily')", $source);
        $this->assertStringContainsString('$families->addChild($admin, $family, $data);', $source);
        $this->assertStringContainsString('Parent tickets are retained', $source);
    }

    public function test_existing_family_link_form_allows_either_parent_but_requires_one(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenTicketResource.php'));
        $start = strpos($source, "Select::make('father_ticket_id')");
        $end = strpos($source, "Forms\\Components\\Repeater::make('children')", $start);
        $parentFields = substr($source, $start, $end - $start);

        $this->assertStringContainsString("->requiredWithout('mother_ticket_id')", $parentFields);
        $this->assertStringContainsString("->requiredWithout('father_ticket_id')", $parentFields);
        $this->assertStringContainsString('Select either parent or both. At least one parent ticket is required.', $parentFields);
        $this->assertStringContainsString('Select a father, a mother, or both.', $parentFields);
        $this->assertStringNotContainsString("->required(),", $parentFields);
    }

    public function test_child_forms_use_required_age_and_gender_fields_with_age_driven_rules(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenTicketResource.php'));

        foreach ([
            "TextInput::make('age')",
            "TextInput::make('child.age')",
            "Select::make('gender')",
            "Select::make('child.gender')",
            "->label('Age')",
            "->minValue(1)",
            "->maxValue(120)",
            "TextInput::make('child.email')",
            "TextInput::make('child.phone')",
            "Toggle::make('child.adult_confirmation')",
            "Select::make('ticket_type_id')",
            "Hidden::make('payment_method')",
            "TextInput::make('voucher_code')",
            "static::childIsAdult(\$get('child.age'))",
            "static::childRequiresPayment(\$get('child.age'))",
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }

        $this->assertStringNotContainsString("DatePicker::make('child.date_of_birth')", $source);
        $this->assertStringNotContainsString("DatePicker::make('date_of_birth')", $source);
        $this->assertStringNotContainsString("Hidden::make('child.date_of_birth')", $source);
        $this->assertStringNotContainsString("Hidden::make('date_of_birth')", $source);
        $this->assertStringNotContainsString('static::ageToDateOfBirth($state)', $source);

        $start = strpos($source, 'public static function existingFamilyChildForm');
        $end = strpos($source, 'private static function linkableTicketOptions', $start);
        $formSource = substr($source, $start, $end - $start);

        $this->assertStringContainsString("Placeholder::make('complimentary_ticket_status')", $formSource);
        $this->assertStringContainsString('Children Complementary Ticket - no payment required.', $formSource);
        $this->assertGreaterThan(
            strpos($formSource, "Section::make('Paid child ticket')"),
            strpos($formSource, "Select::make('ticket_type_id')"),
        );
        $this->assertStringNotContainsString("TextInput::make('wallet_otp')", $formSource);
        $this->assertStringNotContainsString("'wallet' => 'My Goshen wallet'", $formSource);
    }

    public function test_ticket_list_can_filter_family_linked_tickets(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenTicketResource.php'));

        foreach ([
            "TernaryFilter::make('family_linked')",
            "->label('Family link')",
            "->trueLabel('Linked to a family')",
            "->falseLabel('Not linked to a family')",
            "whereIn('attendee_id', GoshenFamilyMember::query()->select('attendee_id')",
            "whereNotIn('attendee_id', GoshenFamilyMember::query()->select('attendee_id')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }
    }
}
