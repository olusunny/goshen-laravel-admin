<?php

namespace Tests\Feature;

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\DynamicFormSubmission;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DynamicFormApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_open_forms_are_listed_with_dynamic_fields(): void
    {
        $form = $this->form('volunteer-interest', [
            'title' => 'Volunteer Interest',
            'description' => 'Tell us where you want to serve.',
        ]);
        $this->field($form, 'department', 'Department', DynamicFormField::TYPE_CHOICE, [
            'options' => ['Media', 'Protocol'],
            'is_required' => true,
        ]);

        $this->getJson('/api/dynamic-forms')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.0.slug', 'volunteer-interest')
            ->assertJsonPath('data.0.fields.0.key', 'department')
            ->assertJsonPath('data.0.fields.0.options.0', 'Media');
    }

    public function test_required_fields_are_validated_before_free_submission_is_saved(): void
    {
        $form = $this->form('transport-request');
        $this->field($form, 'pickup_location', 'Pickup location', DynamicFormField::TYPE_TEXT, [
            'is_required' => true,
        ]);

        $this->postJson('/api/dynamic-forms/transport-request/submit', [
            'data' => [
                'name' => 'Grace Member',
                'email' => 'grace@example.test',
                'answers' => [],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertSame(0, DynamicFormSubmission::query()->count());

        $this->postJson('/api/dynamic-forms/transport-request/submit', [
            'data' => [
                'name' => 'Grace Member',
                'email' => 'grace@example.test',
                'answers' => [
                    'pickup_location' => 'Church office',
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('submission.payment_status', DynamicFormSubmission::PAYMENT_NOT_REQUIRED);

        $submission = DynamicFormSubmission::query()->firstOrFail();
        $this->assertSame('Church office', $submission->answers['pickup_location']['answer']);
    }

    public function test_authenticated_form_requires_mobile_user_token(): void
    {
        $form = $this->form('members-only', [
            'visibility' => DynamicForm::VISIBILITY_AUTHENTICATED,
        ]);
        $this->field($form, 'note', 'Note');

        $this->postJson('/api/dynamic-forms/members-only/submit', [
            'data' => [
                'answers' => ['note' => 'Hello'],
            ],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');

        $member = $this->member();
        $token = $member->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/dynamic-forms/members-only/submit', [
                'data' => [
                    'api_token' => $token,
                    'answers' => ['note' => 'Hello'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok');
    }

    public function test_wallet_paid_form_debits_wallet_and_records_paid_submission(): void
    {
        $member = $this->member();
        $token = $member->issueApiToken();
        $wallet = GoshenWallet::query()->create([
            'mobile_user_id' => $member->id,
            'currency' => 'GBP',
            'balance' => 50,
        ]);

        $form = $this->form('paid-meal', [
            'visibility' => DynamicForm::VISIBILITY_AUTHENTICATED,
            'payment_type' => DynamicForm::PAYMENT_FIXED,
            'fixed_amount' => 15,
            'currency' => 'GBP',
            'allow_wallet' => true,
        ]);
        $this->field($form, 'meal_choice', 'Meal choice', DynamicFormField::TYPE_CHOICE, [
            'options' => ['Rice', 'Pasta'],
            'is_required' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/dynamic-forms/paid-meal/submit', [
                'data' => [
                    'api_token' => $token,
                    'payment_method' => 'wallet',
                    'answers' => [
                        'meal_choice' => 'Rice',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('mode', 'wallet')
            ->assertJsonPath('submission.payment_status', DynamicFormSubmission::PAYMENT_PAID)
            ->assertJsonPath('submission.amount', 15);

        $this->assertSame('35.00', $wallet->fresh()->balance);
        $this->assertDatabaseHas('goshen_wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => 'dynamic_form_payment',
            'status' => 'paid',
            'amount' => 15,
        ]);
    }

    public function test_paid_form_submission_answers_are_immutable(): void
    {
        $form = $this->form('locked-paid-form', [
            'payment_type' => DynamicForm::PAYMENT_FIXED,
            'fixed_amount' => 10,
        ]);

        $submission = DynamicFormSubmission::query()->create([
            'dynamic_form_id' => $form->id,
            'reference' => 'dfs_locked_test',
            'answers' => [
                'name' => [
                    'key' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'answer' => 'Grace',
                ],
            ],
            'status' => DynamicFormSubmission::STATUS_PAID,
            'payment_status' => DynamicFormSubmission::PAYMENT_PAID,
            'payment_provider' => 'stripe',
            'amount' => 10,
            'currency' => 'GBP',
            'provider_reference' => 'dfs_locked_test',
            'paid_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Paid form submissions cannot be edited.');

        $submission->forceFill([
            'answers' => [
                'name' => [
                    'key' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'answer' => 'Changed',
                ],
            ],
        ])->save();
    }

    public function test_file_upload_answers_are_stored_privately(): void
    {
        Storage::fake('local');

        $form = $this->form('proof-upload');
        $this->field($form, 'proof', 'Upload proof', DynamicFormField::TYPE_FILE, [
            'is_required' => true,
            'settings' => [
                'allowed_extensions' => ['pdf'],
                'max_kb' => 512,
            ],
        ]);

        $this->post('/api/dynamic-forms/proof-upload/submit', [
            'data' => json_encode(['answers' => []]),
            'files' => [
                'proof' => UploadedFile::fake()->create('proof.pdf', 12, 'application/pdf'),
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok');

        $submission = DynamicFormSubmission::query()->firstOrFail();
        $answer = $submission->answers['proof']['answer'];

        $this->assertSame('local', $answer['disk']);
        $this->assertArrayHasKey('file_path', $answer);
        $this->assertArrayNotHasKey('file_url', $answer);
        Storage::disk('local')->assertExists($answer['file_path']);
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

    private function field(DynamicForm $form, string $key, string $label, string $type = DynamicFormField::TYPE_TEXT, array $overrides = []): DynamicFormField
    {
        return $form->fields()->create(array_merge([
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'is_required' => false,
            'sort_order' => 0,
        ], $overrides));
    }

    private function member(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Grace Member',
            'email' => 'member@example.test',
            'phone' => '+447700900123',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }
}
