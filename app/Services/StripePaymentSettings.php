<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

class StripePaymentSettings
{
    public const MODE_TEST = 'test';

    public const MODE_LIVE = 'live';

    public function applyToConfig(): void
    {
        $secret = $this->secretKey();
        $givingWebhookSecret = $this->givingWebhookSecret();
        $eventWebhookSecret = $this->eventWebhookSecret();
        $walletWebhookSecret = $this->walletWebhookSecret();
        $apiVersion = $this->apiVersion();

        config([
            'services.stripe.secret' => $secret ?: config('services.stripe.secret'),
            'services.stripe.webhook_secret' => $givingWebhookSecret ?: config('services.stripe.webhook_secret'),
            'services.stripe.wallet_webhook_secret' => $walletWebhookSecret ?: config('services.stripe.wallet_webhook_secret'),
            'services.stripe.api_version' => $apiVersion,
            'services.stripe.success_url' => $this->givingSuccessUrl(),
            'services.stripe.cancel_url' => $this->givingCancelUrl(),
            'services.stripe.wallet_success_url' => $this->walletSuccessUrl(),
            'services.stripe.wallet_cancel_url' => $this->walletCancelUrl(),
            'event-installments.payments.stripe.secret' => $secret ?: config('event-installments.payments.stripe.secret'),
            'event-installments.payments.stripe.webhook_secret' => $eventWebhookSecret ?: config('event-installments.payments.stripe.webhook_secret'),
            'event-installments.payments.stripe.api_version' => $apiVersion,
            'event-installments.payments.stripe.success_url' => $this->eventSuccessUrl(),
            'event-installments.payments.stripe.cancel_url' => $this->eventCancelUrl(),
        ]);

        if ($secret !== '') {
            config(['event-installments.payments.default_gateway' => 'stripe']);
        }
    }

    public function mode(): string
    {
        $mode = $this->setting('stripe_mode', self::MODE_TEST);

        return in_array($mode, [self::MODE_TEST, self::MODE_LIVE], true)
            ? $mode
            : self::MODE_TEST;
    }

    public function apiVersion(): string
    {
        return $this->setting('stripe_api_version', (string) config('services.stripe.api_version', '2026-02-25.clover'));
    }

    public function publishableKey(?string $mode = null): string
    {
        return $this->setting($this->modeKey($mode, 'publishable_key'), '');
    }

    public function secretKey(?string $mode = null): string
    {
        return $this->secret($this->modeKey($mode, 'secret_key'), (string) config('services.stripe.secret', ''));
    }

    public function webhookSecret(?string $mode = null): string
    {
        return $this->secret($this->modeKey($mode, 'webhook_secret'), (string) config('services.stripe.webhook_secret', ''));
    }

    public function givingWebhookSecret(?string $mode = null): string
    {
        $legacy = $this->webhookSecret($mode);

        return $this->secret($this->modeKey($mode, 'giving_webhook_secret'), $legacy);
    }

    public function eventWebhookSecret(?string $mode = null): string
    {
        $legacy = $this->webhookSecret($mode);

        return $this->secret($this->modeKey($mode, 'event_webhook_secret'), $legacy);
    }

    public function walletWebhookSecret(?string $mode = null): string
    {
        $legacy = $this->eventWebhookSecret($mode);

        return $this->secret($this->modeKey($mode, 'wallet_webhook_secret'), $legacy);
    }

    public function givingSuccessUrl(): string
    {
        return $this->setting('stripe_giving_success_url', $this->configuredUrl('services.stripe.success_url', url('/app?giving=success&session_id={CHECKOUT_SESSION_ID}')));
    }

    public function givingCancelUrl(): string
    {
        return $this->setting('stripe_giving_cancel_url', $this->configuredUrl('services.stripe.cancel_url', url('/app?giving=cancelled')));
    }

    public function eventSuccessUrl(): string
    {
        return $this->setting('stripe_event_success_url', $this->configuredUrl('event-installments.payments.stripe.success_url', url('/app/payments?checkout=success&session_id={CHECKOUT_SESSION_ID}')));
    }

    public function eventCancelUrl(): string
    {
        return $this->setting('stripe_event_cancel_url', $this->configuredUrl('event-installments.payments.stripe.cancel_url', url('/app/payments?checkout=cancelled')));
    }

    public function walletSuccessUrl(): string
    {
        return $this->setting('stripe_wallet_success_url', $this->configuredUrl('services.stripe.wallet_success_url', url('/app/wallet?wallet=success&session_id={CHECKOUT_SESSION_ID}')));
    }

    public function walletCancelUrl(): string
    {
        return $this->setting('stripe_wallet_cancel_url', $this->configuredUrl('services.stripe.wallet_cancel_url', url('/app/wallet?wallet=cancelled')));
    }

    public function configured(?string $mode = null): bool
    {
        return $this->secretKey($mode) !== ''
            && $this->givingWebhookSecret($mode) !== ''
            && $this->eventWebhookSecret($mode) !== ''
            && $this->walletWebhookSecret($mode) !== ''
            && $this->givingSuccessUrl() !== ''
            && $this->givingCancelUrl() !== ''
            && $this->eventSuccessUrl() !== ''
            && $this->eventCancelUrl() !== ''
            && $this->walletSuccessUrl() !== ''
            && $this->walletCancelUrl() !== '';
    }

    public function maskedSecretStatus(?string $mode = null): string
    {
        $secret = $this->secretKey($mode);

        if ($secret === '') {
            return 'Not configured';
        }

        return str_starts_with($secret, 'sk_live_')
            ? 'Live secret saved'
            : 'Test secret saved';
    }

    public function save(array $data): void
    {
        $mode = in_array($data['mode'] ?? self::MODE_TEST, [self::MODE_TEST, self::MODE_LIVE], true)
            ? $data['mode']
            : self::MODE_TEST;

        $this->put('stripe_mode', $mode, 'payments', false, 'Active Stripe mode used for Giving and Goshen Retreat payments.');
        $this->put('stripe_api_version', trim((string) ($data['api_version'] ?? $this->apiVersion())), 'payments', false, 'Stripe API version used by the admin payment integrations.');
        $this->put('stripe_giving_success_url', trim((string) ($data['giving_success_url'] ?? '')), 'payments', false, 'Return URL after successful Giving checkout.');
        $this->put('stripe_giving_cancel_url', trim((string) ($data['giving_cancel_url'] ?? '')), 'payments', false, 'Return URL after cancelled Giving checkout.');
        $this->put('stripe_event_success_url', trim((string) ($data['event_success_url'] ?? '')), 'payments', false, 'Return URL after successful Goshen event checkout.');
        $this->put('stripe_event_cancel_url', trim((string) ($data['event_cancel_url'] ?? '')), 'payments', false, 'Return URL after cancelled Goshen event checkout.');
        $this->put('stripe_wallet_success_url', trim((string) ($data['wallet_success_url'] ?? '')), 'payments', false, 'Return URL after successful Goshen Wallet checkout.');
        $this->put('stripe_wallet_cancel_url', trim((string) ($data['wallet_cancel_url'] ?? '')), 'payments', false, 'Return URL after cancelled Goshen Wallet checkout.');

        foreach ([self::MODE_TEST, self::MODE_LIVE] as $stripeMode) {
            $this->put($this->modeKey($stripeMode, 'publishable_key'), trim((string) ($data[$stripeMode.'_publishable_key'] ?? '')), 'payments', false, ucfirst($stripeMode).' Stripe publishable key.');

            if (! empty($data[$stripeMode.'_secret_key'])) {
                $this->putSecret($this->modeKey($stripeMode, 'secret_key'), trim((string) $data[$stripeMode.'_secret_key']), ucfirst($stripeMode).' Stripe secret key.');
            }

            if (! empty($data[$stripeMode.'_webhook_secret'])) {
                $this->putSecret($this->modeKey($stripeMode, 'webhook_secret'), trim((string) $data[$stripeMode.'_webhook_secret']), ucfirst($stripeMode).' Stripe webhook signing secret.');
            }

            if (! empty($data[$stripeMode.'_giving_webhook_secret'])) {
                $this->putSecret($this->modeKey($stripeMode, 'giving_webhook_secret'), trim((string) $data[$stripeMode.'_giving_webhook_secret']), ucfirst($stripeMode).' Stripe Giving webhook signing secret.');
            }

            if (! empty($data[$stripeMode.'_event_webhook_secret'])) {
                $this->putSecret($this->modeKey($stripeMode, 'event_webhook_secret'), trim((string) $data[$stripeMode.'_event_webhook_secret']), ucfirst($stripeMode).' Stripe Goshen Retreat webhook signing secret.');
            }

            if (! empty($data[$stripeMode.'_wallet_webhook_secret'])) {
                $this->putSecret($this->modeKey($stripeMode, 'wallet_webhook_secret'), trim((string) $data[$stripeMode.'_wallet_webhook_secret']), ucfirst($stripeMode).' Stripe Goshen Wallet webhook signing secret.');
            }
        }
    }

    public function resetMode(string $mode): void
    {
        if (! in_array($mode, [self::MODE_TEST, self::MODE_LIVE], true)) {
            throw new RuntimeException('Invalid Stripe mode.');
        }

        AppSetting::query()
            ->whereIn('key', [
                $this->modeKey($mode, 'publishable_key'),
                $this->modeKey($mode, 'secret_key'),
                $this->modeKey($mode, 'webhook_secret'),
                $this->modeKey($mode, 'giving_webhook_secret'),
                $this->modeKey($mode, 'event_webhook_secret'),
                $this->modeKey($mode, 'wallet_webhook_secret'),
            ])
            ->delete();
    }

    public function testConnection(?string $mode = null): array
    {
        $mode ??= $this->mode();
        $secret = $this->secretKey($mode);

        if ($secret === '') {
            throw new RuntimeException('Stripe '.$mode.' secret key is not configured.');
        }

        $client = new StripeClient([
            'api_key' => $secret,
            'stripe_version' => $this->apiVersion(),
        ]);

        $balance = $client->balance->retrieve();
        $payload = $balance->toArray();

        return [
            'mode' => $mode,
            'object' => $payload['object'] ?? 'balance',
            'available_count' => count($payload['available'] ?? []),
        ];
    }

    private function modeKey(?string $mode, string $suffix): string
    {
        $mode = in_array($mode ?: $this->mode(), [self::MODE_TEST, self::MODE_LIVE], true)
            ? ($mode ?: $this->mode())
            : self::MODE_TEST;

        return 'stripe_'.$mode.'_'.$suffix;
    }

    private function setting(string $key, string $default = ''): string
    {
        $value = AppSetting::query()->where('key', $key)->value('value');

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function secret(string $key, string $default = ''): string
    {
        $value = AppSetting::query()->where('key', $key)->value('value');

        if (! is_string($value) || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }

    private function configuredUrl(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function put(string $key, string $value, string $group, bool $secret, ?string $description = null): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $value,
                'is_secret' => $secret,
                'description' => $description,
            ],
        );
    }

    private function putSecret(string $key, string $value, ?string $description = null): void
    {
        $this->put($key, Crypt::encryptString($value), 'payments', true, $description);
    }
}
