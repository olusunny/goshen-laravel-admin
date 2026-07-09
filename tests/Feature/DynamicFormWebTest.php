<?php

namespace Tests\Feature;

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\DynamicFormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_forms_are_available_on_web(): void
    {
        $form = $this->form('choir-interest', [
            'title' => 'Choir Interest',
            'description' => 'Tell us if you want to join choir.',
        ]);
        $this->field($form, 'voice_part', 'Voice part', DynamicFormField::TYPE_CHOICE, [
            'options' => ['Soprano', 'Alto'],
        ]);

        $this->get('/forms')
            ->assertOk()
            ->assertSee('Goshen Forms')
            ->assertSee('Choir Interest');

        $this->get('/forms/choir-interest')
            ->assertOk()
            ->assertSee('Voice part')
            ->assertSee('Please Select');
    }

    public function test_web_free_form_submission_creates_response(): void
    {
        $form = $this->form('transport-interest');
        $this->field($form, 'pickup', 'Pickup', DynamicFormField::TYPE_TEXT, [
            'is_required' => true,
        ]);

        $this->post('/forms/transport-interest', [
            'answers' => [
                'pickup' => 'Woolwich',
            ],
        ])
            ->assertRedirect('/forms/transport-interest');

        $submission = DynamicFormSubmission::query()->firstOrFail();

        $this->assertSame(DynamicFormSubmission::PAYMENT_NOT_REQUIRED, $submission->payment_status);
        $this->assertSame('Woolwich', $submission->answers['pickup']['answer']);
    }

    public function test_authenticated_only_forms_are_not_exposed_on_public_web(): void
    {
        $this->form('private-form', [
            'visibility' => DynamicForm::VISIBILITY_AUTHENTICATED,
        ]);

        $this->get('/forms/private-form')->assertNotFound();
    }

    private function form(string $slug, array $overrides = []): DynamicForm
    {
        return DynamicForm::query()->create(array_merge([
            'title' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'is_active' => true,
            'visibility' => DynamicForm::VISIBILITY_PUBLIC,
            'payment_type' => DynamicForm::PAYMENT_FREE,
            'currency' => 'GBP',
            'allow_stripe' => true,
            'allow_wallet' => true,
        ], $overrides));
    }

    private function field(DynamicForm $form, string $key, string $label, string $type, array $overrides = []): DynamicFormField
    {
        return $form->fields()->create(array_merge([
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'is_required' => false,
            'sort_order' => 0,
        ], $overrides));
    }
}
