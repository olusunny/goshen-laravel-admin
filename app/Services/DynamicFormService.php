<?php

namespace App\Services;

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\DynamicFormSubmission;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class DynamicFormService
{
    public function __construct(
        private readonly StripePaymentSettings $stripeSettings,
        private readonly GoshenWalletService $wallets,
        private readonly WalletSecurityResetService $walletSecurityResets,
    ) {}

    public function formPayload(DynamicForm $form, ?MobileUser $user = null, bool $includeResponses = false): array
    {
        $form->loadMissing('fields');

        $payload = [
            'id' => $form->id,
            'title' => $form->title,
            'slug' => $form->slug,
            'description' => $form->description,
            'is_active' => (bool) $form->is_active,
            'is_open' => $form->isOpen(),
            'visibility' => $form->visibility,
            'requires_login' => $form->requiresLogin(),
            'one_submission_per_user' => (bool) $form->one_submission_per_user,
            'max_submissions' => $form->max_submissions,
            'opens_at' => $form->opens_at?->toIso8601String(),
            'closes_at' => $form->closes_at?->toIso8601String(),
            'submit_button_label' => $form->submit_button_label ?: 'Submit',
            'thank_you_message' => $form->thank_you_message,
            'payment' => [
                'type' => $form->payment_type,
                'required' => $form->requiresPayment(),
                'amount' => $form->requiresPayment() ? (float) $form->fixed_amount : 0,
                'currency' => $form->currency,
                'allow_stripe' => (bool) $form->allow_stripe,
                'allow_wallet' => (bool) $form->allow_wallet,
            ],
            'fields' => $form->fields
                ->map(fn (DynamicFormField $field): array => $this->fieldPayload($field))
                ->values()
                ->all(),
            'already_submitted' => $this->alreadySubmitted($form, $user),
        ];

        if ($includeResponses) {
            $payload['submissions_count'] = $form->submissions()
                ->whereIn('status', [DynamicFormSubmission::STATUS_SUBMITTED, DynamicFormSubmission::STATUS_PAID])
                ->count();
        }

        return $payload;
    }

    public function fieldPayload(DynamicFormField $field): array
    {
        $settings = is_array($field->settings) ? $field->settings : [];
        if ($field->type === DynamicFormField::TYPE_FILE) {
            $settings['allowed_extensions'] = $this->normalizeAllowedExtensions(
                $settings['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            );
        }

        return [
            'id' => $field->id,
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type,
            'placeholder' => $field->placeholder,
            'help_text' => $field->help_text,
            'options' => $this->fieldOptions($field),
            'settings' => $settings ?: (object) [],
            'conditional_logic' => $field->conditional_logic ?: (object) [],
            'is_required' => (bool) $field->is_required,
            'sort_order' => (int) $field->sort_order,
        ];
    }

    public function submissionPayload(DynamicFormSubmission $submission, bool $includeFileLinks = false): array
    {
        $submission->loadMissing('dynamicForm');

        return [
            'id' => $submission->id,
            'reference' => $submission->reference,
            'form_id' => $submission->dynamic_form_id,
            'form_title' => $submission->dynamicForm?->title,
            'form_slug' => $submission->dynamicForm?->slug,
            'name' => $submission->name,
            'email' => $submission->email,
            'phone' => $submission->phone,
            'answers' => $includeFileLinks
                ? $this->answersWithTemporaryFileLinks($submission)
                : ($submission->answers ?: (object) []),
            'status' => $submission->status,
            'payment_status' => $submission->payment_status,
            'payment_provider' => $submission->payment_provider,
            'amount' => $submission->amount !== null ? (float) $submission->amount : null,
            'currency' => $submission->currency,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'paid_at' => $submission->paid_at?->toIso8601String(),
        ];
    }

    public function normalizeAllowedExtensions(mixed $extensions): array
    {
        $items = is_array($extensions) ? $extensions : [$extensions];

        return collect($items)
            ->flatMap(function (mixed $extension): array {
                if (is_array($extension)) {
                    return $extension;
                }

                return preg_split('/[\s,;|]+/', (string) $extension) ?: [];
            })
            ->map(fn (mixed $extension): string => strtolower(ltrim(trim((string) $extension), '.')))
            ->filter(fn (string $extension): bool => $extension !== '' && preg_match('/^[a-z0-9]+$/', $extension) === 1)
            ->unique()
            ->values()
            ->all();
    }

    public function submit(DynamicForm $form, Request $request, ?MobileUser $user = null, string $source = 'api'): array
    {
        $form->loadMissing('fields');
        $this->assertFormAvailable($form, $user);

        $payload = $this->payload($request);
        $answers = $this->normalizedAnswers($form, $request, $payload['answers'] ?? $payload);
        $contact = $this->contactPayload($payload, $user, $form->requiresPayment());
        $paymentMethod = strtolower(trim((string) ($payload['payment_method'] ?? '')));

        if (! $form->requiresPayment()) {
            return [
                'mode' => 'submitted',
                'submission' => $this->createSubmittedSubmission($form, $user, $contact, $answers, $source),
            ];
        }

        if ($paymentMethod === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Please choose a payment method for this form.',
            ]);
        }

        return match ($paymentMethod) {
            'wallet' => [
                'mode' => 'wallet',
                'submission' => $this->createWalletPaidSubmission($form, $user, $contact, $answers, $source),
            ],
            'stripe', 'card' => [
                'mode' => 'stripe',
                ...$this->createStripeSubmission($form, $user, $contact, $answers, $source),
            ],
            default => throw ValidationException::withMessages([
                'payment_method' => 'Please choose wallet or card checkout for this form.',
            ]),
        };
    }

    public function settleStripeWebhook(array $payload): ?DynamicFormSubmission
    {
        $object = data_get($payload, 'data.object', []);
        $reference = (string) (data_get($object, 'client_reference_id')
            ?: data_get($object, 'metadata.dynamic_form_reference'));

        if ($reference === '') {
            return null;
        }

        return DB::transaction(function () use ($payload, $object, $reference): ?DynamicFormSubmission {
            $submission = DynamicFormSubmission::query()
                ->where('payment_provider', 'stripe')
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $submission) {
                return null;
            }

            if (($submission->metadata['stripe_last_event_id'] ?? null) === ($payload['id'] ?? null)) {
                return $submission;
            }

            if ($submission->payment_status === DynamicFormSubmission::PAYMENT_PAID) {
                return $submission;
            }

            $status = $this->stripeEventStatus($payload, $object);
            $metadata = array_merge($submission->metadata ?? [], [
                'stripe_last_event_id' => $payload['id'] ?? null,
                'stripe_last_event_type' => $payload['type'] ?? null,
                'stripe_last_event_at' => now()->toIso8601String(),
                'stripe_session_id' => data_get($object, 'id'),
                'stripe_payment_intent' => data_get($object, 'payment_intent'),
            ]);

            $updates = ['metadata' => $metadata];
            if ($status === DynamicFormSubmission::PAYMENT_PAID) {
                $verification = $this->verifyStripeObject($submission, $object);
                if (! $verification['valid']) {
                    $submission->forceFill([
                        'metadata' => array_merge($metadata, [
                            'stripe_verification_error' => $verification['message'],
                        ]),
                    ])->save();

                    return $submission;
                }

                $updates['status'] = DynamicFormSubmission::STATUS_PAID;
                $updates['payment_status'] = DynamicFormSubmission::PAYMENT_PAID;
                $updates['paid_at'] = now();
                $updates['submitted_at'] = $submission->submitted_at ?: now();
            } elseif ($status === DynamicFormSubmission::PAYMENT_FAILED) {
                $updates['status'] = DynamicFormSubmission::STATUS_FAILED;
                $updates['payment_status'] = DynamicFormSubmission::PAYMENT_FAILED;
            }

            $submission->forceFill($updates)->save();

            return $submission->fresh(['dynamicForm', 'mobileUser']);
        });
    }

    private function createSubmittedSubmission(
        DynamicForm $form,
        ?MobileUser $user,
        array $contact,
        array $answers,
        string $source,
    ): DynamicFormSubmission {
        return DB::transaction(function () use ($form, $user, $contact, $answers, $source): DynamicFormSubmission {
            $lockedForm = DynamicForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $this->assertFormAvailable($lockedForm, $user);

            return DynamicFormSubmission::query()->create([
                'dynamic_form_id' => $lockedForm->id,
                'mobile_user_id' => $user?->id,
                'reference' => $this->newReference('df_'),
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'answers' => $answers,
                'status' => DynamicFormSubmission::STATUS_SUBMITTED,
                'payment_status' => DynamicFormSubmission::PAYMENT_NOT_REQUIRED,
                'submitted_at' => now(),
                'metadata' => ['source' => $source],
            ]);
        });
    }

    private function createWalletPaidSubmission(
        DynamicForm $form,
        ?MobileUser $user,
        array $contact,
        array $answers,
        string $source,
    ): DynamicFormSubmission {
        if (! $form->allow_wallet) {
            throw new RuntimeException('Wallet payment is not available for this form.');
        }

        if (! $user) {
            throw new RuntimeException('Please sign in before paying this form from your wallet.');
        }

        if (! $user->canUseCommunity()) {
            throw new RuntimeException('Please verify your email address before using wallet payment.');
        }

        $this->walletSecurityResets->assertWalletActionsAllowed($user);

        $amount = round((float) $form->fixed_amount, 2);
        $currency = strtoupper((string) $form->currency);
        $reference = $this->newReference('dfw_');

        return DB::transaction(function () use ($form, $user, $contact, $answers, $source, $amount, $currency, $reference): DynamicFormSubmission {
            $lockedForm = DynamicForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $this->assertFormAvailable($lockedForm, $user);

            $wallet = $this->wallets->walletFor($user);
            $lockedWallet = GoshenWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if (strtoupper((string) $lockedWallet->currency) !== $currency) {
                throw new RuntimeException('Your wallet currency does not match this form payment.');
            }

            if ((float) $lockedWallet->balance + 0.01 < $amount) {
                throw new RuntimeException('Your wallet balance is not enough for this form payment.');
            }

            $lockedWallet->forceFill([
                'balance' => round(((float) $lockedWallet->balance) - $amount, 2),
            ])->save();

            $submission = DynamicFormSubmission::query()->create([
                'dynamic_form_id' => $lockedForm->id,
                'mobile_user_id' => $user->id,
                'reference' => $reference,
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'answers' => $answers,
                'status' => DynamicFormSubmission::STATUS_PENDING_PAYMENT,
                'payment_status' => DynamicFormSubmission::PAYMENT_PENDING,
                'payment_provider' => 'wallet',
                'amount' => $amount,
                'currency' => $currency,
                'provider_reference' => $reference,
                'metadata' => [
                    'source' => $source,
                    'wallet_id' => $lockedWallet->id,
                    'wallet_balance_after' => (float) $lockedWallet->balance,
                ],
            ]);

            $entry = $lockedWallet->ledgerEntries()->create([
                'type' => 'dynamic_form_payment',
                'status' => 'paid',
                'currency' => $currency,
                'amount' => $amount,
                'gateway' => 'wallet',
                'provider_reference' => $reference,
                'metadata' => [
                    'source' => 'dynamic_form',
                    'dynamic_form_id' => $lockedForm->id,
                    'dynamic_form_title' => $lockedForm->title,
                    'dynamic_form_submission_id' => $submission->id,
                    'submission_reference' => $submission->reference,
                ],
                'settled_at' => now(),
            ]);

            $submission->forceFill([
                'wallet_ledger_entry_id' => $entry->id,
                'status' => DynamicFormSubmission::STATUS_PAID,
                'payment_status' => DynamicFormSubmission::PAYMENT_PAID,
                'paid_at' => now(),
                'submitted_at' => now(),
            ])->save();

            return $submission->fresh(['dynamicForm', 'mobileUser', 'walletLedgerEntry']) ?? $submission;
        });
    }

    private function createStripeSubmission(
        DynamicForm $form,
        ?MobileUser $user,
        array $contact,
        array $answers,
        string $source,
    ): array {
        if (! $form->allow_stripe) {
            throw new RuntimeException('Card checkout is not available for this form.');
        }

        $this->assertStripeConfigured();

        $amount = round((float) $form->fixed_amount, 2);
        $currency = strtoupper((string) $form->currency);
        $reference = $this->newReference('dfs_');

        $submission = DB::transaction(function () use ($form, $user, $contact, $answers, $source, $amount, $currency, $reference): DynamicFormSubmission {
            $lockedForm = DynamicForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $this->assertFormAvailable($lockedForm, $user);

            return DynamicFormSubmission::query()->create([
                'dynamic_form_id' => $lockedForm->id,
                'mobile_user_id' => $user?->id,
                'reference' => $reference,
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'answers' => $answers,
                'status' => DynamicFormSubmission::STATUS_PENDING_PAYMENT,
                'payment_status' => DynamicFormSubmission::PAYMENT_PENDING,
                'payment_provider' => 'stripe',
                'amount' => $amount,
                'currency' => $currency,
                'provider_reference' => $reference,
                'metadata' => ['source' => $source],
            ]);
        });

        $metadata = [
            'source' => 'dynamic-form',
            'dynamic_form_id' => (string) $form->id,
            'dynamic_form_slug' => (string) $form->slug,
            'dynamic_form_submission_id' => (string) $submission->id,
            'dynamic_form_reference' => $reference,
            'mobile_user_id' => (string) $user?->id,
        ];

        try {
            $session = $this->stripe()->checkout->sessions->create([
                'mode' => 'payment',
                'success_url' => $this->stripeSettings->givingSuccessUrl(),
                'cancel_url' => $this->stripeSettings->givingCancelUrl(),
                'client_reference_id' => $reference,
                'customer_email' => $contact['email'] ?: null,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => $metadata,
                'payment_intent_data' => ['metadata' => $metadata],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $this->toMinorUnits($amount, $currency),
                        'product_data' => [
                            'name' => $form->title,
                        ],
                    ],
                ]],
            ], [
                'idempotency_key' => $reference,
            ]);
        } catch (ApiErrorException|RuntimeException $exception) {
            $submission->forceFill([
                'status' => DynamicFormSubmission::STATUS_FAILED,
                'payment_status' => DynamicFormSubmission::PAYMENT_FAILED,
                'metadata' => array_merge($submission->metadata ?? [], [
                    'stripe_checkout_error' => $exception->getMessage(),
                    'stripe_checkout_failed_at' => now()->toIso8601String(),
                ]),
            ])->save();

            throw new RuntimeException('Secure card checkout is not available right now. Please try again shortly.', 0, $exception);
        }

        $payload = $session->toArray();
        $checkoutUrl = (string) ($payload['url'] ?? '');

        $submission->forceFill([
            'metadata' => array_merge($submission->metadata ?? [], [
                'stripe_checkout_session_id' => $payload['id'] ?? null,
                'stripe_checkout_url' => $checkoutUrl,
                'stripe_checkout_url_created_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return [
            'submission' => $submission->fresh(['dynamicForm', 'mobileUser']) ?? $submission,
            'checkout' => [
                'gateway' => 'stripe',
                'reference' => $reference,
                'checkout_url' => $checkoutUrl,
            ],
        ];
    }

    private function assertFormAvailable(DynamicForm $form, ?MobileUser $user): void
    {
        if (! $form->isOpen()) {
            throw new RuntimeException('This form is not accepting submissions right now.');
        }

        if ($form->requiresLogin() && ! $user) {
            throw new RuntimeException('Please sign in before submitting this form.');
        }

        if ($form->max_submissions !== null) {
            $count = $form->submissions()
                ->whereIn('status', [DynamicFormSubmission::STATUS_SUBMITTED, DynamicFormSubmission::STATUS_PAID])
                ->count();
            if ($count >= (int) $form->max_submissions) {
                throw new RuntimeException('This form has reached its submission limit.');
            }
        }

        if (! $form->one_submission_per_user) {
            return;
        }

        if ($user) {
            $exists = $form->submissions()
                ->where('mobile_user_id', $user->id)
                ->where('payment_status', '!=', DynamicFormSubmission::PAYMENT_FAILED)
                ->exists();
            if ($exists) {
                throw new RuntimeException('You have already submitted this form.');
            }
        }
    }

    private function alreadySubmitted(DynamicForm $form, ?MobileUser $user): bool
    {
        if (! $user) {
            return false;
        }

        return $form->submissions()
            ->where('mobile_user_id', $user->id)
            ->where('payment_status', '!=', DynamicFormSubmission::PAYMENT_FAILED)
            ->exists();
    }

    private function contactPayload(array $payload, ?MobileUser $user, bool $required): array
    {
        $contact = [
            'name' => trim((string) ($payload['name'] ?? $user?->name ?? '')),
            'email' => trim((string) ($payload['email'] ?? $user?->email ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? $user?->phone ?? '')),
        ];

        $errors = [];
        if ($required && $contact['name'] === '') {
            $errors['name'] = 'Please enter your full name.';
        }
        if ($required && ($contact['email'] === '' || ! filter_var($contact['email'], FILTER_VALIDATE_EMAIL))) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($contact['email'] !== '' && ! filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $contact;
    }

    private function normalizedAnswers(DynamicForm $form, Request $request, mixed $rawAnswers): array
    {
        $answers = is_array($rawAnswers) ? $rawAnswers : [];
        $normalized = [];

        foreach ($form->fields as $field) {
            if (! $this->fieldIsVisible($field, $answers)) {
                continue;
            }

            $key = (string) $field->key;
            $value = $this->decodedAnswerValue($answers[$key] ?? $answers[$field->id] ?? null);
            $file = $this->fieldFile($request, $field);

            if ($field->type === DynamicFormField::TYPE_FILE) {
                if ($field->is_required && ! $file) {
                    throw ValidationException::withMessages([$key => "Please upload: {$field->label}"]);
                }

                if (! $file) {
                    continue;
                }

                $value = $this->storeFieldFile($form, $field, $file);
            } else {
                $this->validateScalarField($field, $value);
            }

            if ($this->answerIsBlank($value, $field)) {
                continue;
            }

            $normalized[$key] = [
                'field_id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'answer' => $value,
            ];
        }

        return $normalized;
    }

    private function validateScalarField(DynamicFormField $field, mixed &$value): void
    {
        $key = $field->key;
        $required = (bool) $field->is_required;

        if ($required && $this->answerIsBlank($value, $field)) {
            throw ValidationException::withMessages([$key => "Please answer: {$field->label}"]);
        }

        if ($this->answerIsBlank($value, $field)) {
            return;
        }

        if (in_array($field->type, $this->choiceFieldTypes(), true)) {
            $options = $this->fieldOptions($field);
            $allowed = collect($options)->map(fn ($option) => $this->optionValue($option))->filter()->values();
            $values = $field->type === DynamicFormField::TYPE_MULTI_CHOICE
                ? (is_array($value) ? $value : [$value])
                : [$value];

            $unknown = collect($values)->filter(fn ($item) => ! $allowed->contains((string) $item));
            if ($unknown->isNotEmpty()) {
                throw ValidationException::withMessages([$key => "Please choose a valid option for: {$field->label}"]);
            }

            if (in_array($field->type, [DynamicFormField::TYPE_IMAGE_CHOICE, DynamicFormField::TYPE_COLOR_CHOICE], true)) {
                $value = $this->selectedOptionAnswer($field, (string) $value);
            }

            return;
        }

        if (in_array($field->type, [DynamicFormField::TYPE_CHECKBOX, DynamicFormField::TYPE_CONSENT], true)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            if ($required && ! $value) {
                throw ValidationException::withMessages([$key => "Please confirm: {$field->label}"]);
            }

            return;
        }

        if ($field->type === DynamicFormField::TYPE_EMAIL && ! filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([$key => "Please enter a valid email for: {$field->label}"]);
        }

        if ($field->type === DynamicFormField::TYPE_NUMBER && ! is_numeric($value)) {
            throw ValidationException::withMessages([$key => "Please enter a valid number for: {$field->label}"]);
        }

        if ($field->type === DynamicFormField::TYPE_DATE) {
            try {
                $value = Carbon::parse((string) $value)->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages([$key => "Please enter a valid date for: {$field->label}"]);
            }
        }

        if (is_string($value)) {
            $max = $this->textMaxLength($field);
            if (mb_strlen($value) > $max) {
                throw ValidationException::withMessages([$key => "{$field->label} must not be longer than {$max} characters."]);
            }
        }
    }

    private function textMaxLength(DynamicFormField $field): int
    {
        $fallback = $field->type === DynamicFormField::TYPE_TEXTAREA ? 5000 : 255;
        $configured = data_get($field->settings, 'max_length');

        if ($configured === null || $configured === '') {
            return $fallback;
        }

        if (! is_numeric($configured)) {
            return $fallback;
        }

        $max = (int) $configured;
        if ($max < 1) {
            return $fallback;
        }

        return min(10000, $max);
    }

    private function fieldIsVisible(DynamicFormField $field, array $answers): bool
    {
        $logic = is_array($field->conditional_logic) ? $field->conditional_logic : [];
        if (! filter_var($logic['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $sourceKey = trim((string) ($logic['field_key'] ?? $logic['question_key'] ?? ''));
        if ($sourceKey === '') {
            return true;
        }

        $sourceValue = $this->decodedAnswerValue($answers[$sourceKey] ?? null);
        $operator = (string) ($logic['operator'] ?? 'equals');
        $expected = trim((string) ($logic['value'] ?? ''));
        $values = $this->answerComparisonValues($sourceValue);
        $answered = collect($values)->contains(fn (string $value): bool => trim($value) !== '');

        return match ($operator) {
            'answered' => $answered,
            'not_answered' => ! $answered,
            'not_equals' => ! collect($values)->contains(fn (string $value): bool => strcasecmp(trim($value), $expected) === 0),
            'contains' => collect($values)->contains(fn (string $value): bool => str_contains(strtolower($value), strtolower($expected))),
            'not_contains' => ! collect($values)->contains(fn (string $value): bool => str_contains(strtolower($value), strtolower($expected))),
            default => collect($values)->contains(fn (string $value): bool => strcasecmp(trim($value), $expected) === 0),
        };
    }

    private function answerIsBlank(mixed $value, DynamicFormField $field): bool
    {
        if (in_array($field->type, [DynamicFormField::TYPE_CHECKBOX, DynamicFormField::TYPE_CONSENT], true)) {
            return $value === null || $value === '';
        }

        return blank($value);
    }

    private function fieldOptions(DynamicFormField $field): array
    {
        if ($field->type === DynamicFormField::TYPE_IMAGE_CHOICE) {
            return collect(data_get($field->settings, 'image_options', []))
                ->map(function (mixed $option): ?array {
                    if (! is_array($option)) {
                        return null;
                    }

                    $label = trim((string) ($option['label'] ?? ''));
                    $imagePath = $this->storedMediaPath($option['image_path'] ?? null);
                    if ($label === '' || $imagePath === null) {
                        return null;
                    }

                    return [
                        'label' => $label,
                        'value' => $this->optionValue($option),
                        'image_path' => $imagePath,
                        'image_url' => $this->publicStorageUrl($imagePath),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        if ($field->type === DynamicFormField::TYPE_COLOR_CHOICE) {
            return collect(data_get($field->settings, 'color_options', []))
                ->map(function (mixed $option): ?array {
                    if (! is_array($option)) {
                        return null;
                    }

                    $label = trim((string) ($option['label'] ?? ''));
                    if ($label === '') {
                        return null;
                    }

                    return [
                        'label' => $label,
                        'value' => $this->optionValue($option),
                        'color_hex' => $this->normalizeColorHex($option['color_hex'] ?? null),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        return is_array($field->options) ? $field->options : [];
    }

    private function selectedOptionAnswer(DynamicFormField $field, string $value): array
    {
        $option = collect($this->fieldOptions($field))
            ->first(fn ($option): bool => $this->optionValue($option) === $value);

        $answer = [
            'value' => $value,
            'label' => $this->optionLabel($option),
        ];

        if ($field->type === DynamicFormField::TYPE_IMAGE_CHOICE && is_array($option)) {
            $imagePath = $this->storedMediaPath($option['image_path'] ?? null);
            if ($imagePath !== null) {
                $answer['image_path'] = $imagePath;
                $answer['image_url'] = $this->publicStorageUrl($imagePath);
            }
        }

        if ($field->type === DynamicFormField::TYPE_COLOR_CHOICE && is_array($option)) {
            $answer['color_hex'] = $this->normalizeColorHex($option['color_hex'] ?? null);
        }

        return $answer;
    }

    private function choiceFieldTypes(): array
    {
        return [
            DynamicFormField::TYPE_CHOICE,
            DynamicFormField::TYPE_MULTI_CHOICE,
            DynamicFormField::TYPE_IMAGE_CHOICE,
            DynamicFormField::TYPE_COLOR_CHOICE,
        ];
    }

    private function fieldFile(Request $request, DynamicFormField $field): ?UploadedFile
    {
        $key = $field->key;
        $file = $request->file("files.{$key}")
            ?: $request->file("answers.{$key}")
            ?: $request->file($key);

        return $file instanceof UploadedFile ? $file : null;
    }

    private function storeFieldFile(DynamicForm $form, DynamicFormField $field, UploadedFile $file): array
    {
        $maxKb = max(1, min(51200, (int) data_get($field->settings, 'max_kb', 10240)));
        if ($file->getSize() > ($maxKb * 1024)) {
            throw ValidationException::withMessages([$field->key => "{$field->label} must not be larger than {$maxKb}KB."]);
        }

        $allowed = collect($this->normalizeAllowedExtensions(
            data_get($field->settings, 'allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'webp']),
        ));
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($allowed->isNotEmpty() && ! $allowed->contains($extension)) {
            throw ValidationException::withMessages([$field->key => "{$field->label} must be one of: {$allowed->implode(', ')}."]);
        }

        $path = $file->storeAs(
            "dynamic-forms/{$form->id}",
            Str::uuid()->toString() . '.' . $extension,
            'local',
        );

        return [
            'disk' => 'local',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function answersWithTemporaryFileLinks(DynamicFormSubmission $submission): array|object
    {
        $answers = $submission->answers;
        if (! is_array($answers)) {
            return (object) [];
        }

        foreach ($answers as $key => $answer) {
            $payload = data_get($answer, 'answer');
            if (! is_array($payload) || blank($payload['file_path'] ?? null)) {
                continue;
            }

            $payload['download_url'] = URL::temporarySignedRoute(
                'dynamic-form-submissions.files.signed',
                now()->addMinutes(30),
                ['submission' => $submission->id, 'field' => (string) $key],
            );
            $answer['answer'] = $payload;
            $answers[$key] = $answer;
        }

        return $answers;
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

    private function decodedAnswerValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    private function answerComparisonValues(mixed $value): array
    {
        if (is_array($value)) {
            if (array_key_exists('value', $value)) {
                return [(string) $value['value']];
            }

            return collect($value)
                ->flatten()
                ->map(fn ($item): string => (string) $item)
                ->all();
        }

        if ($value === null) {
            return [''];
        }

        return [(string) $value];
    }

    private function optionValue(mixed $option): string
    {
        if (is_array($option)) {
            $value = trim((string) ($option['value'] ?? ''));
            if ($value !== '') {
                return $value;
            }

            return trim((string) ($option['label'] ?? ''));
        }

        return trim((string) $option);
    }

    private function optionLabel(mixed $option): string
    {
        if (is_array($option)) {
            $label = trim((string) ($option['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }

            return $this->optionValue($option);
        }

        return trim((string) $option);
    }

    private function storedMediaPath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path);
        }

        $path = trim((string) $path);

        return $path === '' ? null : $path;
    }

    private function normalizeColorHex(mixed $color): string
    {
        $color = trim((string) $color);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $color) === 1) {
            return '#' . strtolower(ltrim($color, '#'));
        }

        return '#ffffff';
    }

    private function publicStorageUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    private function assertStripeConfigured(): void
    {
        $this->stripeSettings->applyToConfig();
        if ($this->stripeSettings->secretKey() === '') {
            throw new RuntimeException('Secure card checkout is not configured yet.');
        }
    }

    private function stripe(): StripeClient
    {
        return new StripeClient([
            'api_key' => $this->stripeSettings->secretKey(),
            'stripe_version' => $this->stripeSettings->apiVersion(),
        ]);
    }

    private function stripeEventStatus(array $payload, array $object): ?string
    {
        $type = (string) ($payload['type'] ?? '');
        $paymentStatus = (string) data_get($object, 'payment_status', '');

        return match (true) {
            in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'payment_intent.succeeded'], true),
                $paymentStatus === 'paid' => DynamicFormSubmission::PAYMENT_PAID,
            in_array($type, ['checkout.session.async_payment_failed', 'payment_intent.payment_failed', 'checkout.session.expired'], true) => DynamicFormSubmission::PAYMENT_FAILED,
            default => null,
        };
    }

    private function verifyStripeObject(DynamicFormSubmission $submission, array $object): array
    {
        $currency = strtoupper((string) data_get($object, 'currency', $submission->currency));
        $amountTotal = data_get($object, 'amount_total');
        $paidAmount = $amountTotal !== null
            ? ((float) $amountTotal) / $this->minorUnitMultiplier((string) $submission->currency)
            : (float) $submission->amount;

        if ($currency !== strtoupper((string) $submission->currency)) {
            return ['valid' => false, 'message' => 'Stripe currency did not match the form payment.'];
        }

        if ($paidAmount + 0.01 < (float) $submission->amount) {
            return ['valid' => false, 'message' => 'Stripe amount did not match the form payment.'];
        }

        return ['valid' => true, 'message' => null];
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        return (int) round($amount * $this->minorUnitMultiplier($currency));
    }

    private function minorUnitMultiplier(string $currency): int
    {
        return in_array(strtoupper($currency), ['JPY', 'KRW'], true) ? 1 : 100;
    }

    private function newReference(string $prefix): string
    {
        return $prefix . Str::ulid();
    }
}
