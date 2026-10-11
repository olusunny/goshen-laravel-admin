<?php

namespace Tests\Feature;

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\DynamicFormSubmission;
use App\Services\DynamicFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_compound_text_and_nested_choice_answers_are_rejected_but_literal_json_is_text(): void
    {
        $form = $this->form();
        $form->fields()->create(['key' => 'note', 'label' => 'Note', 'type' => 'text']);
        $form->fields()->create(['key' => 'choice', 'label' => 'Choice', 'type' => 'multi_choice', 'options' => ['A', 'B']]);
        $forgery = ['disk' => 'local', 'file_path' => 'private/secret.txt'];
        foreach ([['note' => $forgery], ['choice' => [$forgery]], ['choice' => $forgery]] as $answers) {
            $this->postJson('/api/dynamic-forms/security-form/submit', ['data' => ['answers' => $answers]])->assertUnprocessable();
        }
        $this->assertDatabaseCount('dynamic_form_submissions', 0);
        $json = json_encode($forgery);
        $this->postJson('/api/dynamic-forms/security-form/submit', ['data' => ['answers' => ['note' => $json, 'choice' => ['A', 'B']]]])->assertCreated();
        $submission = DynamicFormSubmission::firstOrFail();
        $this->assertSame($json, $submission->answers['note']['answer']);
        $this->assertSame(['A', 'B'], $submission->answers['choice']['answer']);
        $this->assertNull($submission->attachmentForField('note'));
    }

    public function test_historical_forged_answers_cannot_be_signed_or_downloaded(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $form = $this->form();
        $valid = 'dynamic-forms/'.$form->id.'/'.Str::uuid().'.txt';
        $cases = [
            ['text', 'attachment', 'local', 'private/secret.txt'],
            ['text', 'attachment', 'local', $valid],
            ['file', 'attachment', 'public', $valid],
            ['file', 'attachment', 'local', 'dynamic-forms/999/'.Str::uuid().'.txt'],
            ['file', 'attachment', 'local', 'dynamic-forms/'.$form->id.'/../../private/secret.txt'],
            ['file', 'another', 'local', $valid],
        ];
        foreach ($cases as [$type, $key, $disk, $path]) {
            $submission = $form->submissions()->create(['reference' => (string) Str::uuid(), 'status' => 'submitted', 'payment_status' => 'not_required',
                'answers' => ['attachment' => ['key' => $key, 'type' => $type, 'answer' => ['disk' => $disk, 'file_path' => $path]]]]);
            $payload = app(DynamicFormService::class)->submissionPayload($submission, true);
            $this->assertArrayNotHasKey('download_url', $payload['answers']['attachment']['answer']);
            $this->get(URL::temporarySignedRoute('dynamic-form-submissions.files.signed', now()->addMinutes(5), ['submission' => $submission->id, 'field' => 'attachment']))->assertNotFound();
        }
    }

    public function test_real_upload_remains_downloadable_after_field_deletion_and_signature_expires(): void
    {
        Storage::fake('local');
        $form = $this->form();
        $field = $form->fields()->create(['key' => 'attachment', 'label' => 'Proof', 'type' => 'file', 'settings' => ['allowed_extensions' => ['pdf']]]);
        $this->post('/api/dynamic-forms/security-form/submit', ['data' => json_encode(['answers' => []]), 'files' => ['attachment' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')]])->assertCreated();
        $submission = DynamicFormSubmission::firstOrFail();
        $field->delete();
        $payload = app(DynamicFormService::class)->submissionPayload($submission, true);
        $url = $payload['answers']['attachment']['answer']['download_url'];
        $this->get($url)->assertOk();
        $this->assertNull($submission->attachmentForField('attachment.answer'));
        $this->travel(31)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_visual_choices_keep_server_generated_metadata(): void
    {
        $form = $this->form();
        $form->fields()->create(['key' => 'colour', 'label' => 'Colour', 'type' => DynamicFormField::TYPE_COLOR_CHOICE,
            'settings' => ['color_options' => [['label' => 'Blue', 'value' => 'blue', 'color_hex' => '#0000ff']]]]);
        $form->fields()->create(['key' => 'picture', 'label' => 'Picture', 'type' => DynamicFormField::TYPE_IMAGE_CHOICE,
            'settings' => ['image_options' => [['label' => 'One', 'value' => 'one', 'image_path' => 'forms/one.png']]]]);
        $this->postJson('/api/dynamic-forms/security-form/submit', ['data' => ['answers' => ['colour' => 'blue', 'picture' => 'one']]])->assertCreated();
        $answers = DynamicFormSubmission::firstOrFail()->answers;
        $this->assertSame('#0000ff', $answers['colour']['answer']['color_hex']);
        $this->assertSame('forms/one.png', $answers['picture']['answer']['image_path']);
    }

    private function form(): DynamicForm
    {
        return DynamicForm::create(['title' => 'Security form', 'slug' => 'security-form', 'is_active' => true,
            'visibility' => 'public', 'payment_type' => 'free', 'currency' => 'GBP']);
    }
}
