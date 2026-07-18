<?php

namespace App\Http\Controllers\Api;

use App\Models\AppSetting;
use App\Models\Donation;
use App\Models\DonationCategory;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\GoshenWalletService;
use App\Services\DynamicFormService;
use App\Services\StripePaymentSettings;
use App\Services\WalletSecurityResetService;
use App\Support\StripeAppReturnUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Sunny\Fundraising\Services\ContributionService as FundraisingContributionService;
use Throwable;
use UnexpectedValueException;

class DonationStripeController extends Controller
{
    private bool $stripeSettingsApplied = false;

    public function __construct(private readonly StripePaymentSettings $stripeSettings) {}

    public function status(): JsonResponse
    {
        $this->applyStripeSettings();

        return response()->json([
            'status' => 'ok',
            'enabled' => $this->enabled(),
            'configured' => $this->configured(),
            'wallet_enabled' => $this->walletGivingEnabled(),
            'wallet_configured' => $this->walletGivingEnabled(),
            'currency' => $this->defaultCurrency(),
            'categories' => $this->activeCategories()
                ->map(fn (DonationCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ])
                ->values(),
        ]);
    }

    public function payWithWallet(
        Request $request,
        GoshenWalletService $wallets,
        WalletSecurityResetService $walletSecurityResets,
    ): JsonResponse {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Online giving is not available right now.',
            ], 404);
        }

        if (! $this->walletGivingEnabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wallet giving is not available right now.',
            ], 503);
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'donation_category_id' => ['required_without:category_slug', 'nullable', 'integer'],
            'category_slug' => ['required_without:donation_category_id', 'nullable', 'string', 'max:140'],
            'purpose' => ['nullable', 'string', 'max:180'],
            'anonymous' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'api_token' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $user = $this->mobileUserFromRequest($request, $validated);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before giving from your wallet.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before using wallet giving.',
            ], 403);
        }

        try {
            $walletSecurityResets->assertWalletActionsAllowed($user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'wallet_security_reset' => $walletSecurityResets->statusPayload($user),
            ], 423);
        }

        $currency = strtoupper((string) ($validated['currency'] ?? $this->defaultCurrency()));
        $category = $this->resolveCategory($validated);
        $purpose = $category->name;
        $amount = round((float) $validated['amount'], 2);
        $anonymous = false;
        $idempotencyKey = trim((string) $validated['idempotency_key']);
        $reference = 'give_wallet_' . substr(hash('sha256', $user->id . '|' . $idempotencyKey), 0, 32);

        try {
            $result = DB::transaction(function () use ($wallets, $user, $category, $purpose, $amount, $currency, $anonymous, $reference, $idempotencyKey): array {
                $wallet = $wallets->walletFor($user);
                $lockedWallet = GoshenWallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingDonation = Donation::query()
                    ->with('category')
                    ->where('provider', 'wallet')
                    ->where('reference', $reference)
                    ->lockForUpdate()
                    ->first();

                if ($existingDonation) {
                    if (! $this->walletDonationMatches($existingDonation, $user, $category, $amount, $currency)) {
                        throw new RuntimeException('This wallet giving request was already used for different giving details.');
                    }

                    return [
                        'donation' => $existingDonation,
                        'wallet' => $lockedWallet->fresh(),
                        'idempotent_replay' => true,
                    ];
                }

                if (strtoupper((string) $lockedWallet->currency) !== $currency) {
                    throw new RuntimeException('Your wallet currency does not match this giving currency.');
                }

                if ((float) $lockedWallet->balance + 0.01 < $amount) {
                    throw new RuntimeException('Your wallet balance is not enough for this giving.');
                }

                $donation = Donation::query()->create([
                    'name' => $anonymous ? null : $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'donation_category_id' => $category->id,
                    'purpose' => $purpose,
                    'amount' => $amount,
                    'currency' => $currency,
                    'provider' => 'wallet',
                    'reference' => $reference,
                    'status' => 'pending',
                    'metadata' => [
                        'source' => 'goshen_flutter_app',
                        'payment_method' => 'wallet',
                        'mobile_user_id' => $user->id,
                        'wallet_id' => $lockedWallet->id,
                        'idempotency_key_hash' => hash('sha256', $idempotencyKey),
                        'purpose' => $purpose,
                        'donation_category_id' => $category->id,
                        'category_slug' => $category->slug,
                        'category_name_snapshot' => $category->name,
                        'anonymous' => $anonymous,
                    ],
                ]);

                $lockedWallet->forceFill([
                    'balance' => round(((float) $lockedWallet->balance) - $amount, 2),
                ])->save();

                $entry = $lockedWallet->ledgerEntries()->create([
                    'type' => 'giving_payment',
                    'status' => 'paid',
                    'currency' => $currency,
                    'amount' => $amount,
                    'gateway' => 'wallet',
                    'provider_reference' => $reference,
                    'metadata' => [
                        'source' => 'goshen_wallet_giving',
                        'donation_id' => $donation->id,
                        'donation_reference' => $donation->reference,
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                        'anonymous' => $anonymous,
                    ],
                    'settled_at' => now(),
                ]);

                $donation->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'metadata' => array_merge($donation->metadata ?? [], [
                        'wallet_ledger_entry_id' => $entry->id,
                        'wallet_balance_after' => (float) $lockedWallet->balance,
                    ]),
                ])->save();

                return [
                    'donation' => $donation->fresh(['category']),
                    'wallet' => $lockedWallet->fresh(),
                    'idempotent_replay' => false,
                ];
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Wallet giving could not be completed right now. Please try again shortly.',
            ], 500);
        }

        $donation = $result['donation'];

        return response()->json([
            'status' => 'ok',
            'message' => 'Your giving has been recorded from your wallet.',
            'donation' => [
                'reference' => $donation->reference,
                'amount' => (float) $donation->amount,
                'currency' => $donation->currency,
                'status' => $donation->status,
                'payment_method' => 'wallet',
                'category' => $donation->category?->name ?? $category->name,
            ],
            'idempotent_replay' => (bool) ($result['idempotent_replay'] ?? false),
            'wallet' => $wallets->payload($result['wallet']),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $this->applyStripeSettings();

        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Online giving is not available right now.',
            ], 404);
        }

        if (! $this->configured()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Online giving is not configured yet. Please contact the church office.',
            ], 503);
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'donation_category_id' => ['required_without:category_slug', 'nullable', 'integer'],
            'category_slug' => ['required_without:donation_category_id', 'nullable', 'string', 'max:140'],
            'purpose' => ['nullable', 'string', 'max:180'],
            'anonymous' => ['nullable', 'boolean'],
            'api_token' => ['nullable', 'string', 'max:255'],
            'return_to_app' => ['nullable', 'boolean'],
        ])->validate();

        $user = $this->mobileUserFromRequest($request, $validated);
        $name = trim((string) ($validated['name'] ?? $user?->name ?? ''));
        $email = trim((string) ($validated['email'] ?? $user?->email ?? ''));
        $phone = trim((string) ($validated['phone'] ?? $user?->phone ?? ''));

        $contactErrors = [];
        if ($name === '') {
            $contactErrors['name'] = 'Please enter your full name.';
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contactErrors['email'] = 'Please enter a valid email address.';
        }
        if ($phone === '') {
            $contactErrors['phone'] = 'Please enter your phone number.';
        }

        if ($contactErrors !== []) {
            throw ValidationException::withMessages($contactErrors);
        }

        $currency = strtoupper((string) ($validated['currency'] ?? $this->defaultCurrency()));
        $category = $this->resolveCategory($validated);
        $purpose = $category->name;
        $reference = 'give_' . Str::ulid();
        $anonymous = false;

        $metadata = [
            'source' => 'goshen_flutter_app',
            'giver_type' => $user ? 'member' : 'visitor',
            'purpose' => $purpose,
            'donation_category_id' => $category->id,
            'category_slug' => $category->slug,
            'category_name_snapshot' => $category->name,
            'anonymous' => $anonymous,
        ];

        if ($user) {
            $metadata['mobile_user_id'] = $user->id;
        }

        $returnUrls = StripeAppReturnUrls::requested($validated)
            ? StripeAppReturnUrls::giving()
            : [
                'success_url' => $this->requiredConfig('success_url'),
                'cancel_url' => $this->requiredConfig('cancel_url'),
            ];

        $donation = Donation::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'donation_category_id' => $category->id,
            'purpose' => $purpose,
            'amount' => $validated['amount'],
            'currency' => $currency,
            'provider' => 'stripe',
            'reference' => $reference,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        $stripeMetadata = [
            'donation_reference' => $reference,
            'donation_id' => (string) $donation->id,
            'source' => 'goshen-stripe-giving',
        ];

        try {
            $payload = $this->createCheckoutSession([
                'mode' => 'payment',
                'success_url' => $returnUrls['success_url'],
                'cancel_url' => $returnUrls['cancel_url'],
                'client_reference_id' => $reference,
                'customer_email' => $donation->email ?: null,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => $stripeMetadata,
                'payment_intent_data' => ['metadata' => $stripeMetadata],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $this->toMinorUnits((float) $donation->amount, $currency),
                        'product_data' => [
                            'name' => $purpose,
                        ],
                    ],
                ]],
            ], [
                'idempotency_key' => $reference,
            ]);
        } catch (ApiErrorException|RuntimeException $exception) {
            $donation->forceFill([
                'metadata' => array_merge($donation->metadata ?? [], [
                    'checkout_error' => $exception->getMessage(),
                ]),
            ])->save();

            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Secure checkout is not available right now. Please try again shortly.',
            ], 500);
        }

        $donation->forceFill([
            'metadata' => array_merge($donation->metadata ?? [], [
                'stripe_checkout_session_id' => $payload['id'] ?? null,
                'stripe_checkout_url_created_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Secure giving checkout is ready.',
            'donation' => [
                'reference' => $donation->reference,
                'amount' => (float) $donation->amount,
                'currency' => $donation->currency,
                'status' => $donation->status,
                'category' => $category->name,
            ],
            'checkout' => [
                'gateway' => 'stripe',
                'reference' => $reference,
                'checkout_url' => $payload['url'] ?? null,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $user = $this->mobileUserFromRequest($request, $data);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to view your giving history.',
            ], 401);
        }

        $email = strtolower((string) $user->email);

        $donations = Donation::query()
            ->with('category')
            ->where(function ($query) use ($user, $email) {
                $query->where('email', $user->email)
                    ->orWhere('email', $email)
                    ->orWhere('metadata->mobile_user_id', $user->id);
            })
            ->latest()
            ->limit(75)
            ->get()
            ->filter(function (Donation $donation) use ($user, $email): bool {
                $metadataUserId = (int) data_get($donation->metadata, 'mobile_user_id');
                $donationEmail = strtolower((string) $donation->email);

                return $metadataUserId === (int) $user->id
                    || ($email !== '' && $donationEmail === $email);
            })
            ->take(50)
            ->map(fn (Donation $donation): array => $this->historyPayload($donation))
            ->values();

        return response()->json([
            'status' => 'ok',
            'data' => $donations,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $this->applyStripeSettings();

        $secret = $this->requiredConfig('webhook_secret');
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret, 300);
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            report($exception);

            return response()->json(['status' => 'error', 'message' => 'Invalid Stripe webhook.'], 400);
        }

        $eventPayload = $event->toArray();
        $object = $eventPayload['data']['object'] ?? [];
        $reference = $object['client_reference_id']
            ?? data_get($object, 'metadata.donation_reference')
            ?? null;

        if (! is_string($reference) || $reference === '') {
            return response()->json(['status' => 'ok', 'message' => 'No donation reference.']);
        }

        if ($this->isFundraisingStripeEvent($reference, $object)) {
            try {
                app(FundraisingContributionService::class)->settleStripeWebhook($eventPayload);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Fundraising webhook settlement failed.',
                ], 500);
            }

            return response()->json(['status' => 'ok']);
        }

        if (str_starts_with($reference, 'dfs_') || filled(data_get($object, 'metadata.dynamic_form_reference'))) {
            try {
                app(DynamicFormService::class)->settleStripeWebhook($eventPayload);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Dynamic form webhook settlement failed.',
                ], 500);
            }

            return response()->json(['status' => 'ok']);
        }

        $donation = Donation::query()
            ->where('provider', 'stripe')
            ->where('reference', $reference)
            ->first();

        if (! $donation) {
            return response()->json(['status' => 'ok', 'message' => 'Donation not found.']);
        }

        if (($donation->metadata['stripe_last_event_id'] ?? null) === ($eventPayload['id'] ?? null)) {
            return response()->json(['status' => 'ok', 'message' => 'Stripe event already processed.']);
        }

        $status = match ((string) $eventPayload['type']) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'payment_intent.succeeded' => 'paid',
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed',
            'checkout.session.expired' => 'failed',
            default => null,
        };

        if ($donation->isCompleted()) {
            return response()->json(['status' => 'ok', 'message' => 'Completed donation is locked.']);
        }

        $metadata = array_merge($donation->metadata ?? [], [
            'stripe_last_event_id' => $eventPayload['id'] ?? null,
            'stripe_last_event_type' => $eventPayload['type'] ?? null,
            'stripe_last_event_at' => now()->toIso8601String(),
        ]);

        $updates = ['metadata' => $metadata];
        if ($status === 'paid' && $donation->status !== 'paid') {
            $verification = $this->verifyStripePaymentObject($donation, $object);
            if (! $verification['valid']) {
                $donation->forceFill([
                    'metadata' => array_merge($metadata, [
                        'stripe_verification_error' => $verification['message'],
                    ]),
                ])->save();

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Stripe payment event was received but did not match the donation.',
                ]);
            }

            $updates['status'] = 'paid';
            $updates['paid_at'] = now();
        } elseif ($status === 'failed' && $donation->status !== 'paid') {
            $updates['status'] = 'failed';
        }

        $donation->forceFill($updates)->save();

        return response()->json(['status' => 'ok']);
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

    private function mobileUserFromRequest(Request $request, array $data): ?MobileUser
    {
        $token = $data['api_token'] ?? $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return null;
        }

        return MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();
    }

    private function historyPayload(Donation $donation): array
    {
        return [
            'id' => $donation->id,
            'reference' => $donation->reference,
            'purpose' => $donation->purpose,
            'amount' => (float) $donation->amount,
            'currency' => $donation->currency,
            'status' => $donation->status,
            'provider' => $donation->provider,
            'payment_method' => data_get($donation->metadata, 'payment_method') ?: $donation->provider,
            'category' => $donation->category ? [
                'id' => $donation->category->id,
                'name' => $donation->category->name,
                'slug' => $donation->category->slug,
            ] : null,
            'anonymous' => filter_var(data_get($donation->metadata, 'anonymous', false), FILTER_VALIDATE_BOOLEAN),
            'paid_at' => $donation->paid_at?->toIso8601String(),
            'created_at' => $donation->created_at?->toIso8601String(),
            'updated_at' => $donation->updated_at?->toIso8601String(),
        ];
    }

    private function walletDonationMatches(
        Donation $donation,
        MobileUser $user,
        DonationCategory $category,
        float $amount,
        string $currency,
    ): bool {
        return (int) data_get($donation->metadata, 'mobile_user_id') === (int) $user->id
            && (int) $donation->donation_category_id === (int) $category->id
            && strtoupper((string) $donation->currency) === strtoupper($currency)
            && abs(((float) $donation->amount) - $amount) < 0.01;
    }

    private function isFundraisingStripeEvent(string $reference, array $object): bool
    {
        return str_starts_with($reference, 'fund_')
            || data_get($object, 'metadata.source') === 'goshen-stripe-fundraising'
            || filled(data_get($object, 'metadata.fundraising_reference'));
    }

    private function enabled(): bool
    {
        return filter_var(AppSetting::value('goshen_stripe_giving_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    private function walletGivingEnabled(): bool
    {
        return $this->enabled()
            && filter_var(AppSetting::value('goshen_wallet_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    private function activeCategories()
    {
        return DonationCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function resolveCategory(array $validated): ?DonationCategory
    {
        $query = DonationCategory::query()->where('is_active', true);

        if (! empty($validated['donation_category_id'])) {
            $category = (clone $query)->whereKey($validated['donation_category_id'])->first();
            if (! $category) {
                throw ValidationException::withMessages([
                    'donation_category_id' => 'Please select an active giving category.',
                ]);
            }

            return $category;
        }

        if (! empty($validated['category_slug'])) {
            $category = (clone $query)->where('slug', $validated['category_slug'])->first();
            if (! $category) {
                throw ValidationException::withMessages([
                    'category_slug' => 'Please select an active giving category.',
                ]);
            }

            return $category;
        }

        throw ValidationException::withMessages([
            'donation_category_id' => 'Please select an active Giving category.',
        ]);
    }

    private function configured(): bool
    {
        try {
            return $this->requiredConfig('secret') !== ''
                && $this->requiredConfig('webhook_secret') !== ''
                && $this->requiredConfig('success_url') !== ''
                && $this->requiredConfig('cancel_url') !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function verifyStripePaymentObject(Donation $donation, array $object): array
    {
        $stripeCurrency = strtoupper((string) ($object['currency'] ?? ''));
        if ($stripeCurrency !== '' && $stripeCurrency !== strtoupper((string) $donation->currency)) {
            return [
                'valid' => false,
                'message' => 'Stripe currency '.$stripeCurrency.' does not match donation currency '.$donation->currency.'.',
            ];
        }

        $stripeAmount = $object['amount_total']
            ?? $object['amount_received']
            ?? $object['amount']
            ?? null;

        if ($stripeAmount !== null) {
            $expected = $this->toMinorUnits((float) $donation->amount, (string) $donation->currency);
            if ((int) $stripeAmount !== $expected) {
                return [
                    'valid' => false,
                    'message' => 'Stripe amount '.$stripeAmount.' does not match expected amount '.$expected.'.',
                ];
            }
        }

        return ['valid' => true, 'message' => 'Stripe amount and currency verified.'];
    }

    private function stripe(): StripeClient
    {
        $this->applyStripeSettings();

        return new StripeClient([
            'api_key' => $this->requiredConfig('secret'),
            'stripe_version' => config('services.stripe.api_version', '2026-02-25.clover'),
        ]);
    }

    protected function createCheckoutSession(array $payload, array $options): array
    {
        return $this->stripe()
            ->checkout
            ->sessions
            ->create($payload, $options)
            ->toArray();
    }

    private function requiredConfig(string $key): string
    {
        $this->applyStripeSettings();

        $value = config('services.stripe.' . $key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Stripe giving ' . str_replace('_', ' ', $key) . ' is not configured.');
        }

        return $value;
    }

    private function applyStripeSettings(): void
    {
        if ($this->stripeSettingsApplied) {
            return;
        }

        $this->stripeSettings->applyToConfig();
        $this->stripeSettingsApplied = true;
    }

    private function defaultCurrency(): string
    {
        $currency = strtoupper(trim((string) AppSetting::value('currency', 'NGN')));

        return match ($currency) {
            '£' => 'GBP',
            '₦' => 'NGN',
            '$' => 'USD',
            '€' => 'EUR',
            default => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'NGN',
        };
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        return (int) round($amount * $this->minorUnitMultiplier($currency));
    }

    private function minorUnitMultiplier(string $currency): int
    {
        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF',
            'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 1 : 100;
    }
}
