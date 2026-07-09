<?php

namespace App\Http\Controllers\Api;

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\DynamicFormSubmission;
use App\Models\MobileUser;
use App\Services\DynamicFormService;
use App\Services\StripePaymentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class DynamicFormController extends Controller
{
    public function __construct(private readonly DynamicFormService $forms) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);

        $forms = DynamicForm::query()
            ->with('fields')
            ->open()
            ->where(function ($query) use ($user): void {
                $query->where('visibility', DynamicForm::VISIBILITY_PUBLIC);

                if ($user) {
                    $query->orWhere('visibility', DynamicForm::VISIBILITY_AUTHENTICATED);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (DynamicForm $form): array => $this->forms->formPayload($form, $user, includeResponses: true))
            ->values();

        return response()->json([
            'status' => 'ok',
            'message' => 'Forms loaded.',
            'data' => $forms,
        ]);
    }

    public function show(Request $request, string $form): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        $form = $this->formFromKey($form);

        if (! $form || ! $form->isOpen()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be found.',
            ], 404);
        }

        if ($form->requiresLogin() && ! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before opening this form.',
            ], 401);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $this->forms->formPayload($form, $user, includeResponses: true),
        ]);
    }

    public function submit(Request $request, string $form): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        $form = $this->formFromKey($form);

        if (! $form) {
            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be found.',
            ], 404);
        }

        try {
            $result = $this->forms->submit($form, $request, $user, 'flutter_or_web_app');
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?: 'Please check the form and try again.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], in_array($exception->getMessage(), [
                'Please sign in before submitting this form.',
                'Please sign in before paying this form from your wallet.',
            ], true) ? 401 : 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be submitted right now. Please try again shortly.',
            ], 500);
        }

        /** @var DynamicFormSubmission $submission */
        $submission = $result['submission'];
        $checkout = $result['checkout'] ?? null;
        $message = $submission->payment_status === DynamicFormSubmission::PAYMENT_PENDING
            ? 'Secure checkout is ready.'
            : ($form->thank_you_message ?: 'Thank you. Your form has been submitted.');

        return response()->json([
            'status' => 'ok',
            'message' => $message,
            'mode' => $result['mode'],
            'submission' => $this->forms->submissionPayload($submission),
            'checkout' => $checkout,
        ], 201);
    }

    public function managementIndex(Request $request): JsonResponse
    {
        if ($response = $this->authorizeDynamicFormManager($request)) {
            return $response;
        }

        $forms = DynamicForm::query()
            ->with(['fields', 'submissions' => fn ($query) => $query->latest('submitted_at')->latest()->limit(1)])
            ->withCount('submissions')
            ->latest()
            ->get()
            ->map(fn (DynamicForm $form): array => $this->managementFormPayload($form))
            ->values();

        return response()->json([
            'status' => 'ok',
            'message' => 'Dynamic forms loaded.',
            'data' => $forms,
        ]);
    }

    public function managementShow(Request $request, string $form): JsonResponse
    {
        if ($response = $this->authorizeDynamicFormManager($request)) {
            return $response;
        }

        $form = $this->formFromKey($form);
        if (! $form) {
            return response()->json(['status' => 'error', 'message' => 'This form could not be found.'], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $this->managementFormPayload($form),
        ]);
    }

    public function managementStore(Request $request): JsonResponse
    {
        if ($response = $this->authorizeDynamicFormManager($request)) {
            return $response;
        }

        $validated = $this->validateManagementPayload($request);

        try {
            $form = DB::transaction(function () use ($validated): DynamicForm {
                $form = new DynamicForm();
                $this->fillManagedForm($form, $validated);
                $form->save();
                $this->syncManagedFields($form, $validated['fields'] ?? []);

                return $form->fresh(['fields']);
            });
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be created right now.',
            ], 500);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Form created.',
            'data' => $this->managementFormPayload($form),
        ], 201);
    }

    public function managementUpdate(Request $request, string $form): JsonResponse
    {
        if ($response = $this->authorizeDynamicFormManager($request)) {
            return $response;
        }

        $form = $this->formFromKey($form);
        if (! $form) {
            return response()->json(['status' => 'error', 'message' => 'This form could not be found.'], 404);
        }

        $validated = $this->validateManagementPayload($request, $form);

        try {
            $form = DB::transaction(function () use ($form, $validated): DynamicForm {
                $this->fillManagedForm($form, $validated);
                $form->save();

                if (array_key_exists('fields', $validated)) {
                    $this->syncManagedFields($form, $validated['fields'] ?? []);
                }

                return $form->fresh(['fields']);
            });
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'This form could not be updated right now.',
            ], 500);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Form updated.',
            'data' => $this->managementFormPayload($form),
        ]);
    }

    public function managementStatus(Request $request, string $form): JsonResponse
    {
        if ($response = $this->authorizeDynamicFormManager($request)) {
            return $response;
        }

        $form = $this->formFromKey($form);
        if (! $form) {
            return response()->json(['status' => 'error', 'message' => 'This form could not be found.'], 404);
        }

        $validated = validator($this->payload($request), [
            'is_active' => ['required', 'boolean'],
        ])->validate();

        $form->forceFill(['is_active' => (bool) $validated['is_active']])->save();

        return response()->json([
            'status' => 'ok',
            'message' => $form->is_active ? 'Form activated.' : 'Form deactivated.',
            'data' => $this->managementFormPayload($form->fresh(['fields'])),
        ]);
    }

    public function managementDestroy(Request $request, string $form): JsonResponse
    {
        if ($response = $this->authorizeDynamicFormManager($request)) {
            return $response;
        }

        $form = $this->formFromKey($form);
        if (! $form) {
            return response()->json(['status' => 'error', 'message' => 'This form could not be found.'], 404);
        }

        if ($form->submissions()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This form already has submissions. Deactivate it instead so submission records remain intact.',
            ], 422);
        }

        $form->delete();

        return response()->json([
            'status' => 'ok',
            'message' => 'Form deleted.',
        ]);
    }

    public function managementSubmissions(Request $request, string $form): JsonResponse
    {
        if ($response = $this->authorizeDynamicFormManager($request)) {
            return $response;
        }

        $form = $this->formFromKey($form);
        if (! $form) {
            return response()->json(['status' => 'error', 'message' => 'This form could not be found.'], 404);
        }

        $submissions = $form->submissions()
            ->latest('submitted_at')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DynamicFormSubmission $submission): array => $this->forms->submissionPayload($submission, includeFileLinks: true))
            ->values();

        return response()->json([
            'status' => 'ok',
            'message' => 'Submissions loaded.',
            'data' => [
                'form' => $this->managementFormPayload($form),
                'submissions' => $submissions,
            ],
        ]);
    }

    public function webhook(Request $request, StripePaymentSettings $settings): JsonResponse
    {
        $settings->applyToConfig();
        $secret = $settings->givingWebhookSecret();

        if ($secret === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Dynamic form Stripe webhook is not configured.',
            ], 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
                300,
            );
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            report($exception);

            return response()->json(['status' => 'error', 'message' => 'Invalid Stripe webhook.'], 400);
        }

        $this->forms->settleStripeWebhook($event->toArray());

        return response()->json(['status' => 'ok']);
    }

    private function formFromKey(string $key): ?DynamicForm
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        return DynamicForm::query()
            ->with('fields')
            ->when(
                ctype_digit($key),
                fn ($query) => $query->whereKey((int) $key)->orWhere('slug', $key),
                fn ($query) => $query->where('slug', $key),
            )
            ->first();
    }

    private function managementFormPayload(DynamicForm $form): array
    {
        $form->loadMissing('fields');

        $payload = $this->forms->formPayload($form, null, includeResponses: true);
        $payload['submissions_count'] = (int) ($form->submissions_count ?? $form->submissions()->count());
        $latestSubmission = $form->submissions()->latest('submitted_at')->latest()->first();

        $payload['management'] = [
            'total_submissions' => $payload['submissions_count'],
            'submitted_submissions' => $form->submissions()->where('status', DynamicFormSubmission::STATUS_SUBMITTED)->count(),
            'paid_submissions' => $form->submissions()->where('status', DynamicFormSubmission::STATUS_PAID)->count(),
            'pending_payment_submissions' => $form->submissions()->where('status', DynamicFormSubmission::STATUS_PENDING_PAYMENT)->count(),
            'failed_submissions' => $form->submissions()->where('status', DynamicFormSubmission::STATUS_FAILED)->count(),
            'latest_submission_at' => $latestSubmission?->submitted_at?->toIso8601String(),
        ];

        return $payload;
    }

    private function validateManagementPayload(Request $request, ?DynamicForm $form = null): array
    {
        $fieldTypes = [
            DynamicFormField::TYPE_TEXT,
            DynamicFormField::TYPE_TEXTAREA,
            DynamicFormField::TYPE_EMAIL,
            DynamicFormField::TYPE_PHONE,
            DynamicFormField::TYPE_NUMBER,
            DynamicFormField::TYPE_DATE,
            DynamicFormField::TYPE_CHOICE,
            DynamicFormField::TYPE_MULTI_CHOICE,
            DynamicFormField::TYPE_CHECKBOX,
            DynamicFormField::TYPE_CONSENT,
            DynamicFormField::TYPE_IMAGE_CHOICE,
            DynamicFormField::TYPE_COLOR_CHOICE,
            DynamicFormField::TYPE_FILE,
        ];

        return validator($this->payload($request), [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('dynamic_forms', 'slug')->ignore($form?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'visibility' => ['required', Rule::in([DynamicForm::VISIBILITY_PUBLIC, DynamicForm::VISIBILITY_AUTHENTICATED])],
            'one_submission_per_user' => ['sometimes', 'boolean'],
            'max_submissions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'payment_type' => ['required', Rule::in([DynamicForm::PAYMENT_FREE, DynamicForm::PAYMENT_FIXED])],
            'fixed_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'allow_stripe' => ['sometimes', 'boolean'],
            'allow_wallet' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'submit_button_label' => ['nullable', 'string', 'max:80'],
            'thank_you_message' => ['nullable', 'string', 'max:2000'],
            'fields' => ['sometimes', 'array', 'max:80'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:180'],
            'fields.*.key' => ['nullable', 'string', 'max:120'],
            'fields.*.type' => ['required_with:fields', Rule::in($fieldTypes)],
            'fields.*.placeholder' => ['nullable', 'string', 'max:180'],
            'fields.*.help_text' => ['nullable', 'string', 'max:2000'],
            'fields.*.options' => ['nullable'],
            'fields.*.settings' => ['nullable', 'array'],
            'fields.*.conditional_logic' => ['nullable', 'array'],
            'fields.*.is_required' => ['sometimes', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ])->validate();
    }

    private function fillManagedForm(DynamicForm $form, array $data): void
    {
        $paymentType = (string) ($data['payment_type'] ?? DynamicForm::PAYMENT_FREE);
        $amount = $paymentType === DynamicForm::PAYMENT_FIXED ? (float) ($data['fixed_amount'] ?? 0) : null;

        $form->forceFill([
            'title' => trim((string) $data['title']),
            'slug' => $this->uniqueManagedSlug(
                filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $data['title'],
                $form->exists ? $form : null,
            ),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'visibility' => $data['visibility'] ?? DynamicForm::VISIBILITY_PUBLIC,
            'one_submission_per_user' => (bool) ($data['one_submission_per_user'] ?? false),
            'max_submissions' => filled($data['max_submissions'] ?? null) ? (int) $data['max_submissions'] : null,
            'payment_type' => $paymentType,
            'fixed_amount' => $amount,
            'currency' => strtoupper((string) ($data['currency'] ?? 'GBP')),
            'allow_stripe' => (bool) ($data['allow_stripe'] ?? true),
            'allow_wallet' => (bool) ($data['allow_wallet'] ?? true),
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'submit_button_label' => filled($data['submit_button_label'] ?? null) ? trim((string) $data['submit_button_label']) : 'Submit',
            'thank_you_message' => $data['thank_you_message'] ?? null,
        ]);
    }

    private function syncManagedFields(DynamicForm $form, array $fields): void
    {
        $existing = $form->fields()->get()->keyBy('id');
        $keptIds = [];

        foreach (array_values($fields) as $index => $fieldData) {
            $field = isset($fieldData['id']) && $existing->has((int) $fieldData['id'])
                ? $existing->get((int) $fieldData['id'])
                : new DynamicFormField(['dynamic_form_id' => $form->id]);

            $field->forceFill([
                'dynamic_form_id' => $form->id,
                'key' => filled($fieldData['key'] ?? null)
                    ? Str::slug((string) $fieldData['key'], '_')
                    : Str::slug((string) ($fieldData['label'] ?? 'field_'.$index), '_'),
                'label' => trim((string) ($fieldData['label'] ?? 'Untitled field')),
                'type' => $fieldData['type'] ?? DynamicFormField::TYPE_TEXT,
                'placeholder' => $fieldData['placeholder'] ?? null,
                'help_text' => $fieldData['help_text'] ?? null,
                'options' => $this->normalizeManagedFieldOptions($fieldData),
                'settings' => $this->normalizeManagedFieldSettings($field, $fieldData),
                'conditional_logic' => is_array($fieldData['conditional_logic'] ?? null) ? $fieldData['conditional_logic'] : null,
                'is_required' => (bool) ($fieldData['is_required'] ?? false),
                'sort_order' => (int) ($fieldData['sort_order'] ?? ($index + 1)),
            ])->save();

            $keptIds[] = $field->id;
        }

        if ($keptIds !== []) {
            $form->fields()->whereNotIn('id', $keptIds)->delete();
        } else {
            $form->fields()->delete();
        }
    }

    private function normalizeManagedFieldOptions(array $fieldData): array|null
    {
        $type = (string) ($fieldData['type'] ?? DynamicFormField::TYPE_TEXT);
        if (! in_array($type, [DynamicFormField::TYPE_CHOICE, DynamicFormField::TYPE_MULTI_CHOICE], true)) {
            return null;
        }

        $raw = $fieldData['options'] ?? [];
        $items = is_array($raw) ? $raw : preg_split('/[\r\n,]+/', (string) $raw);

        return collect($items)
            ->map(function (mixed $option): string {
                if (is_array($option)) {
                    return trim((string) ($option['label'] ?? $option['value'] ?? ''));
                }

                return trim((string) $option);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeManagedFieldSettings(DynamicFormField $field, array $fieldData): array|null
    {
        $settings = is_array($field->settings) ? $field->settings : [];
        if (is_array($fieldData['settings'] ?? null)) {
            $settings = array_replace_recursive($settings, $fieldData['settings']);
        }

        $type = (string) ($fieldData['type'] ?? $field->type);

        if (array_key_exists('max_length', $settings)) {
            $settings['max_length'] = filled($settings['max_length']) ? max(1, min(10000, (int) $settings['max_length'])) : null;
        }

        if ($type === DynamicFormField::TYPE_FILE) {
            $settings['max_kb'] = max(1, min(51200, (int) ($settings['max_kb'] ?? 10240)));
            $settings['allowed_extensions'] = $this->forms->normalizeAllowedExtensions(
                $settings['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            );
        }

        return $settings === [] ? null : $settings;
    }

    private function uniqueManagedSlug(string $value, ?DynamicForm $form = null): string
    {
        $base = Str::slug($value);
        $base = $base !== '' ? $base : 'form';
        $slug = $base;
        $counter = 2;

        while (DynamicForm::query()
            ->where('slug', $slug)
            ->when($form, fn ($query) => $query->whereKeyNot($form->id))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function authorizeDynamicFormManager(Request $request): ?JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to manage dynamic forms.',
            ], 401);
        }

        if (! $this->canManageDynamicForms($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage dynamic forms.',
            ], 403);
        }

        return null;
    }

    private function canManageDynamicForms(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_dynamic_forms') || $user->can('manage_on_demand_forms') || $user->can('manage_forms')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'formsmanager', 'dynamicformsmanager', 'triumphantitmanager'],
                true,
            ));
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $request->bearerToken() ?: ($data['api_token'] ?? $request->input('api_token'));

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()->where('api_token_hash', hash('sha256', $token))->first();
        $user?->markApiSeen();

        return $user;
    }

    private function payload(Request $request): array
    {
        $payload = $request->input('data', $request->all());
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }
}
