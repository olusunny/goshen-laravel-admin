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

    public function test_child_form_limits_adult_and_payment_fields_to_the_correct_age_bands(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/GoshenTicketResource.php'));

        foreach ([
            "DatePicker::make('child.date_of_birth')",
            "TextInput::make('child.email')",
            "TextInput::make('child.phone')",
            "Toggle::make('child.adult_confirmation')",
            "Select::make('ticket_type_id')",
            "Hidden::make('payment_method')",
            "TextInput::make('voucher_code')",
            "static::childIsAdult(\$get('child.date_of_birth'))",
            "static::childRequiresPayment(\$get('child.date_of_birth'))",
        ] as $expected) {
            $this->assertStringContainsString($expected, $source);
        }

        $start = strpos($source, 'public static function existingFamilyChildForm');
        $end = strpos($source, 'private static function linkableTicketOptions', $start);
        $formSource = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString("TextInput::make('wallet_otp')", $formSource);
        $this->assertStringNotContainsString("'wallet' => 'My Goshen wallet'", $formSource);
    }
}
