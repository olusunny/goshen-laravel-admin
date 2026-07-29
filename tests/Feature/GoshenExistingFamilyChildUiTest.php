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

        $start = strpos($source, 'public static function existingFamilyChildForm');
        $end = strpos($source, 'private static function linkableTicketOptions', $start);
        $formSource = substr($source, $start, $end - $start);

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
