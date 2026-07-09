<?php

namespace Sunny\Fundraising\Services;

use App\Services\StripePaymentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Sunny\Fundraising\Contracts\UserDisplayContract;
use Sunny\Fundraising\Contracts\WalletGatewayContract;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;
use Throwable;

class ContributionService
{
    private bool $stripeSettingsApplied = false;

    public function __construct(
        private readonly WalletGatewayContract $wallets,
        private readonly UserDisplayContract $users,
        private readonly CampaignService $campaigns,
        private readonly StripePaymentSettings $stripeSettings,
    ) {}

    public function contribute(Campaign $campaign, mixed $user, float $amount, ?string $message = null, bool $anonymous = false, ?string $idempotencyKey = null): array
    {
        $amount = $this->normaliseAmount($amount);
        $this->assertContributionAllowed($campaign, $amount);

        $hash = $idempotencyKey ? hash('sha256', $idempotencyKey) : null;

        return DB::transaction(function () use ($campaign, $user, $amount, $message, $anonymous, $hash): array {
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->assertContributionAllowed($lockedCampaign, $amount);

            $existing = $this->findExistingContribution($lockedCampaign, $user, $hash);
            if ($existing) {
                $this->assertContributionMatches($existing, $user, $amount, (string) $lockedCampaign->currency, $message, $anonymous, 'wallet');

                if ($existing->status === CampaignContribution::STATUS_SUCCEEDED) {
                    return [
                        'contribution' => $existing,
                        'campaign' => $this->campaigns->refreshTotals($lockedCampaign),
                        'wallet' => [
                            'balance' => $this->wallets->getBalance($user),
                            'currency' => $existing->currency,
                        ],
                        'idempotent_replay' => true,
                    ];
                }
            }

            $contribution = $existing ?: CampaignContribution::query()->create([
                'campaign_id' => $lockedCampaign->id,
                'user_id' => $user?->getKey(),
                'user_type' => $user ? get_class($user) : null,
                'amount' => $amount,
                'currency' => $lockedCampaign->currency,
                'status' => CampaignContribution::STATUS_PENDING,
                'payment_provider' => 'wallet',
                'is_anonymous' => $anonymous,
                'display_name' => $anonymous ? null : $this->users->displayName($user),
                'message' => $this->cleanMessage($message),
                'idempotency_key_hash' => $hash,
                'metadata' => [
                    'payment_provider' => 'wallet',
                    'source' => 'goshen_flutter_app',
                ],
            ]);

            $debit = $this->wallets->debitForFundraisingContribution($user, $amount, $lockedCampaign, [
                'contribution_id' => $contribution->id,
                'campaign_id' => $lockedCampaign->id,
            ]);

            if (! $debit->success) {
                $contribution->forceFill([
                    'status' => CampaignContribution::STATUS_FAILED,
                    'metadata' => array_merge($contribution->metadata ?? [], [
                        'wallet_error_code' => $debit->errorCode,
                        'wallet_error_message' => $debit->message,
                    ]),
                ])->save();

                throw new RuntimeException($debit->message ?: 'Unable to debit wallet for this contribution.');
            }

            $contribution->forceFill([
                'status' => CampaignContribution::STATUS_SUCCEEDED,
                'provider_reference' => $debit->walletTransactionId ? 'wallet_'.$debit->walletTransactionId : null,
                'wallet_transaction_id' => (string) $debit->walletTransactionId,
                'succeeded_at' => now(),
                'metadata' => array_merge($contribution->metadata ?? [], [
                    'payment_provider' => 'wallet',
                    'wallet_currency' => $debit->currency,
                    'wallet_balance_after' => $debit->newBalance,
                ]),
            ])->save();

            return [
                'contribution' => $contribution->fresh(),
                'campaign' => $this->campaigns->refreshTotals($lockedCampaign),
                'wallet' => [
                    'balance' => $debit->newBalance,
                    'currency' => $debit->currency,
                ],
                'idempotent_replay' => false,
            ];
        });
    }

    public function createStripeCheckout(Campaign $campaign, mixed $user, float $amount, ?string $message = null, bool $anonymous = false, ?string $idempotencyKey = null): array
    {
        $amount = $this->normaliseAmount($amount);
        $this->assertContributionAllowed($campaign, $amount);
        $this->assertSignedInContributor($user);

        if (! $this->stripeConfigured()) {
            throw new RuntimeException('Secure card checkout is not configured for fundraising yet.');
        }

        $hash = $idempotencyKey ? hash('sha256', $idempotencyKey) : null;

        $contribution = DB::transaction(function () use ($campaign, $user, $amount, $message, $anonymous, $hash): CampaignContribution {
            $lockedCampaign = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->assertContributionAllowed($lockedCampaign, $amount);

            $existing = $this->findExistingContribution($lockedCampaign, $user, $hash);
            if ($existing) {
                $this->assertContributionMatches($existing, $user, $amount, (string) $lockedCampaign->currency, $message, $anonymous, 'stripe');

                if ($existing->status === CampaignContribution::STATUS_SUCCEEDED) {
                    throw new RuntimeException('This fundraising support payment has already been recorded.');
                }

                if ($existing->status === CampaignContribution::STATUS_FAILED) {
                    throw new RuntimeException('This checkout attempt failed. Please try again.');
                }

                return $existing;
            }

            return CampaignContribution::query()->create([
                'campaign_id' => $lockedCampaign->id,
                'user_id' => $user?->getKey(),
                'user_type' => $user ? get_class($user) : null,
                'amount' => $amount,
                'currency' => $lockedCampaign->currency,
                'status' => CampaignContribution::STATUS_PENDING,
                'payment_provider' => 'stripe',
                'provider_reference' => 'fund_'.Str::ulid(),
                'is_anonymous' => $anonymous,
                'display_name' => $anonymous ? null : $this->users->displayName($user),
                'message' => $this->cleanMessage($message),
                'idempotency_key_hash' => $hash,
                'metadata' => [
                    'payment_provider' => 'stripe',
                    'source' => 'goshen_flutter_app',
                    'mobile_user_id' => $user?->getKey(),
                ],
            ]);
        });

        $existingCheckoutUrl = trim((string) data_get($contribution->metadata, 'stripe_checkout_url', ''));
        if ($existingCheckoutUrl !== '') {
            return $this->checkoutResult($contribution, $existingCheckoutUrl, true);
        }

        $metadata = [
            'source' => 'goshen-stripe-fundraising',
            'fundraising_reference' => (string) $contribution->provider_reference,
            'fundraising_contribution_id' => (string) $contribution->id,
            'fundraising_campaign_id' => (string) $contribution->campaign_id,
            'mobile_user_id' => (string) $user?->getKey(),
        ];

        try {
            $session = $this->stripe()->checkout->sessions->create([
                'mode' => 'payment',
                'success_url' => $this->requiredStripeConfig('success_url'),
                'cancel_url' => $this->requiredStripeConfig('cancel_url'),
                'client_reference_id' => (string) $contribution->provider_reference,
                'customer_email' => $user?->email ?: null,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => $metadata,
                'payment_intent_data' => ['metadata' => $metadata],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) $contribution->currency),
                        'unit_amount' => $this->toMinorUnits((float) $contribution->amount, (string) $contribution->currency),
                        'product_data' => [
                            'name' => $this->stripeProductName($contribution),
                        ],
                    ],
                ]],
            ], [
                'idempotency_key' => (string) $contribution->provider_reference,
            ]);
        } catch (ApiErrorException|RuntimeException $exception) {
            $contribution->forceFill([
                'status' => CampaignContribution::STATUS_FAILED,
                'metadata' => array_merge($contribution->metadata ?? [], [
                    'stripe_checkout_error' => $exception->getMessage(),
                    'stripe_checkout_failed_at' => now()->toIso8601String(),
                ]),
            ])->save();

            throw new RuntimeException('Secure card checkout is not available right now. Please try again shortly.', 0, $exception);
        }

        $payload = $session->toArray();
        $checkoutUrl = (string) ($payload['url'] ?? '');

        $contribution->forceFill([
            'metadata' => array_merge($contribution->metadata ?? [], [
                'payment_provider' => 'stripe',
                'stripe_checkout_session_id' => $payload['id'] ?? null,
                'stripe_checkout_url' => $checkoutUrl,
                'stripe_checkout_url_created_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $this->checkoutResult($contribution->fresh(), $checkoutUrl, false);
    }

    public function settleStripeWebhook(array $payload): ?CampaignContribution
    {
        $object = data_get($payload, 'data.object', []);
        $reference = (string) (data_get($object, 'client_reference_id')
            ?: data_get($object, 'metadata.fundraising_reference')
            ?: data_get($object, 'metadata.reference'));

        if ($reference === '') {
            return null;
        }

        return DB::transaction(function () use ($payload, $object, $reference): ?CampaignContribution {
            $contribution = CampaignContribution::query()
                ->where('payment_provider', 'stripe')
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $contribution) {
                return null;
            }

            if (($contribution->metadata['stripe_last_event_id'] ?? null) === ($payload['id'] ?? null)) {
                return $contribution;
            }

            if ($contribution->isSucceeded()) {
                return $contribution;
            }

            $eventStatus = $this->stripeEventStatus($payload, $object);
            $metadata = array_merge($contribution->metadata ?? [], [
                'stripe_last_event_id' => $payload['id'] ?? null,
                'stripe_last_event_type' => $payload['type'] ?? null,
                'stripe_last_event_at' => now()->toIso8601String(),
                'stripe_session_id' => data_get($object, 'id'),
                'stripe_payment_intent' => data_get($object, 'payment_intent'),
            ]);

            $updates = ['metadata' => $metadata];
            if ($eventStatus === CampaignContribution::STATUS_SUCCEEDED) {
                $verification = $this->verifyStripePaymentObject($contribution, $object);
                if (! $verification['valid']) {
                    $contribution->forceFill([
                        'metadata' => array_merge($metadata, [
                            'stripe_verification_error' => $verification['message'],
                        ]),
                    ])->save();

                    return $contribution;
                }

                $updates['status'] = CampaignContribution::STATUS_SUCCEEDED;
                $updates['succeeded_at'] = now();
            } elseif ($eventStatus === CampaignContribution::STATUS_FAILED) {
                $updates['status'] = CampaignContribution::STATUS_FAILED;
            }

            $contribution->forceFill($updates)->save();

            if (($updates['status'] ?? null) === CampaignContribution::STATUS_SUCCEEDED) {
                $campaign = $contribution->campaign()->lockForUpdate()->first();
                if ($campaign) {
                    $this->campaigns->refreshTotals($campaign);
                }
            }

            return $contribution->fresh();
        });
    }

    private function normaliseAmount(float $amount): float
    {
        return round($amount, 2);
    }

    private function assertContributionAllowed(Campaign $campaign, float $amount): void
    {
        $minimum = (float) config('fundraising.wallet.minimum_contribution', 1);

        if ($amount < $minimum) {
            throw new RuntimeException('Please enter a valid contribution amount.');
        }

        if (! $campaign->canContribute()) {
            throw new RuntimeException('This fundraising campaign is not accepting contributions right now.');
        }
    }

    private function assertSignedInContributor(mixed $user): void
    {
        if (! $user) {
            throw new RuntimeException('Please sign in before supporting this campaign.');
        }

        if (method_exists($user, 'canUseCommunity') && ! $user->canUseCommunity()) {
            throw new RuntimeException('Your account cannot support this campaign right now.');
        }
    }

    private function findExistingContribution(Campaign $campaign, mixed $user, ?string $hash): ?CampaignContribution
    {
        if (! $hash) {
            return null;
        }

        return CampaignContribution::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $user?->getKey())
            ->where('user_type', $user ? get_class($user) : null)
            ->where('idempotency_key_hash', $hash)
            ->lockForUpdate()
            ->first();
    }

    private function assertContributionMatches(
        CampaignContribution $contribution,
        mixed $user,
        float $amount,
        string $currency,
        ?string $message,
        bool $anonymous,
        string $provider,
    ): void {
        $actualProvider = (string) ($contribution->payment_provider
            ?: data_get($contribution->metadata, 'payment_provider')
            ?: data_get($contribution->metadata, 'payment_gateway')
            ?: 'wallet');

        $matches = (int) $contribution->user_id === (int) $user?->getKey()
            && (string) $contribution->user_type === ($user ? get_class($user) : '')
            && strtoupper((string) $contribution->currency) === strtoupper($currency)
            && abs(((float) $contribution->amount) - $amount) < 0.01
            && (bool) $contribution->is_anonymous === $anonymous
            && trim((string) $contribution->message) === trim((string) $this->cleanMessage($message))
            && $actualProvider === $provider;

        if (! $matches) {
            throw new RuntimeException('This support request was already used for different payment details.');
        }
    }

    private function cleanMessage(?string $message): ?string
    {
        $message = trim((string) $message);

        return $message === '' ? null : $message;
    }

    private function checkoutResult(CampaignContribution $contribution, string $checkoutUrl, bool $idempotentReplay): array
    {
        return [
            'contribution' => $contribution,
            'checkout' => [
                'gateway' => 'stripe',
                'reference' => (string) $contribution->provider_reference,
                'checkout_url' => $checkoutUrl,
            ],
            'campaign' => $this->campaigns->refreshTotals($contribution->campaign()->firstOrFail()),
            'idempotent_replay' => $idempotentReplay,
        ];
    }

    private function stripeProductName(CampaignContribution $contribution): string
    {
        $campaign = $contribution->campaign()->first();
        $title = trim((string) $campaign?->title);

        return $title !== '' ? 'Fundraising: '.$title : 'Fundraising support';
    }

    private function stripeConfigured(): bool
    {
        if (! filter_var(config('fundraising.payments.stripe.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            return $this->requiredStripeConfig('secret') !== ''
                && $this->requiredStripeConfig('webhook_secret') !== ''
                && $this->requiredStripeConfig('success_url') !== ''
                && $this->requiredStripeConfig('cancel_url') !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function stripe(): StripeClient
    {
        $this->applyStripeSettings();

        return new StripeClient([
            'api_key' => $this->requiredStripeConfig('secret'),
            'stripe_version' => config('services.stripe.api_version', '2026-02-25.clover'),
        ]);
    }

    private function requiredStripeConfig(string $key): string
    {
        $this->applyStripeSettings();

        $value = match ($key) {
            'secret' => config('services.stripe.secret'),
            'webhook_secret' => $this->stripeSettings->givingWebhookSecret(),
            'success_url' => trim((string) config('fundraising.payments.stripe.success_url', '')) ?: $this->stripeSettings->givingSuccessUrl(),
            'cancel_url' => trim((string) config('fundraising.payments.stripe.cancel_url', '')) ?: $this->stripeSettings->givingCancelUrl(),
            default => null,
        };

        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Stripe fundraising '.str_replace('_', ' ', $key).' is not configured.');
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

    private function stripeEventStatus(array $payload, array $object): ?string
    {
        return match ((string) ($payload['type'] ?? '')) {
            'checkout.session.completed' => (string) data_get($object, 'payment_status') === 'paid'
                ? CampaignContribution::STATUS_SUCCEEDED
                : null,
            'checkout.session.async_payment_succeeded',
            'payment_intent.succeeded' => CampaignContribution::STATUS_SUCCEEDED,
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed',
            'checkout.session.expired' => CampaignContribution::STATUS_FAILED,
            default => null,
        };
    }

    private function verifyStripePaymentObject(CampaignContribution $contribution, array $object): array
    {
        $stripeCurrency = strtoupper((string) data_get($object, 'currency', ''));
        if ($stripeCurrency !== '' && $stripeCurrency !== strtoupper((string) $contribution->currency)) {
            return [
                'valid' => false,
                'message' => 'Stripe currency '.$stripeCurrency.' does not match fundraising currency '.$contribution->currency.'.',
            ];
        }

        $stripeAmount = data_get($object, 'amount_total')
            ?? data_get($object, 'amount_received')
            ?? data_get($object, 'amount');

        if ($stripeAmount !== null) {
            $expected = $this->toMinorUnits((float) $contribution->amount, (string) $contribution->currency);
            if ((int) $stripeAmount !== $expected) {
                return [
                    'valid' => false,
                    'message' => 'Stripe amount '.$stripeAmount.' does not match expected amount '.$expected.'.',
                ];
            }
        }

        return ['valid' => true, 'message' => 'Stripe amount and currency verified.'];
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
