<?php

namespace App\Filament\Pages;

use App\Services\StripePaymentSettings;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

class PaymentGateways extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payment Gateways';

    protected static ?string $title = 'Payment Gateways';

    protected static ?string $slug = 'payment-gateways';

    protected string $view = 'filament.pages.payment-gateways';

    public string $mode = StripePaymentSettings::MODE_TEST;

    public string $apiVersion = '';

    public string $givingSuccessUrl = '';

    public string $givingCancelUrl = '';

    public string $eventSuccessUrl = '';

    public string $eventCancelUrl = '';

    public string $walletSuccessUrl = '';

    public string $walletCancelUrl = '';

    public string $testPublishableKey = '';

    public string $testSecretKey = '';

    public string $testWebhookSecret = '';

    public string $testGivingWebhookSecret = '';

    public string $testEventWebhookSecret = '';

    public string $testWalletWebhookSecret = '';

    public string $livePublishableKey = '';

    public string $liveSecretKey = '';

    public string $liveWebhookSecret = '';

    public string $liveGivingWebhookSecret = '';

    public string $liveEventWebhookSecret = '';

    public string $liveWalletWebhookSecret = '';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(StripePaymentSettings $settings): void
    {
        $this->fillFromSettings($settings);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole('super_admin')
            || $user->can(AdminPermissions::PAYMENT_GATEWAYS)
        );
    }

    public function save(StripePaymentSettings $settings): void
    {
        $this->resetErrorBag();

        try {
            $validated = validator($this->payloadWithFallbacks($settings), [
                'mode' => ['required', 'in:test,live'],
                'api_version' => ['required', 'string', 'max:80'],
                'giving_success_url' => $this->checkoutUrlRules(),
                'giving_cancel_url' => $this->checkoutUrlRules(),
                'event_success_url' => $this->checkoutUrlRules(),
                'event_cancel_url' => $this->checkoutUrlRules(),
                'wallet_success_url' => $this->checkoutUrlRules(),
                'wallet_cancel_url' => $this->checkoutUrlRules(),
                'test_publishable_key' => ['nullable', 'string', 'max:255'],
                'test_secret_key' => ['nullable', 'string', 'max:255'],
                'test_webhook_secret' => ['nullable', 'string', 'max:255'],
                'test_giving_webhook_secret' => ['nullable', 'string', 'max:255'],
                'test_event_webhook_secret' => ['nullable', 'string', 'max:255'],
                'test_wallet_webhook_secret' => ['nullable', 'string', 'max:255'],
                'live_publishable_key' => ['nullable', 'string', 'max:255'],
                'live_secret_key' => ['nullable', 'string', 'max:255'],
                'live_webhook_secret' => ['nullable', 'string', 'max:255'],
                'live_giving_webhook_secret' => ['nullable', 'string', 'max:255'],
                'live_event_webhook_secret' => ['nullable', 'string', 'max:255'],
                'live_wallet_webhook_secret' => ['nullable', 'string', 'max:255'],
            ], [
                '*.url' => 'Please enter a full URL including https://.',
            ])->validate();
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            Notification::make()
                ->title('Stripe settings not saved')
                ->body($exception->validator->errors()->first() ?: 'Please review the highlighted fields and try again.')
                ->danger()
                ->send();

            return;
        }

        try {
            $settings->save($validated);
            $settings->applyToConfig();
            $this->fillFromSettings($settings);

            Notification::make()
                ->title('Stripe settings saved')
                ->body('The selected Stripe mode, keys, webhook secrets, and checkout URLs are now active for Giving, Goshen Retreat, and Goshen Wallet payments.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Stripe settings not saved')
                ->body($exception->getMessage() ?: 'The server could not save the Stripe settings. Please check the logs and try again.')
                ->danger()
                ->send();
        }
    }

    public function testConnection(StripePaymentSettings $settings): void
    {
        try {
            $result = $settings->testConnection($this->mode);
            Notification::make()
                ->title('Stripe connection verified')
                ->body('Stripe '.$result['mode'].' mode responded successfully.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Stripe connection failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetTestCredentials(StripePaymentSettings $settings): void
    {
        $settings->resetMode(StripePaymentSettings::MODE_TEST);
        $this->fillFromSettings($settings);

        Notification::make()->title('Test credentials reset')->success()->send();
    }

    public function resetLiveCredentials(StripePaymentSettings $settings): void
    {
        $settings->resetMode(StripePaymentSettings::MODE_LIVE);
        $this->fillFromSettings($settings);

        Notification::make()->title('Live credentials reset')->success()->send();
    }

    public function stripeStatus(string $mode): string
    {
        return app(StripePaymentSettings::class)->maskedSecretStatus($mode);
    }

    public function webhookStatus(string $mode, string $type): string
    {
        $settings = app(StripePaymentSettings::class);
        $secret = $type === 'event'
            ? $settings->eventWebhookSecret($mode)
            : ($type === 'wallet'
                ? $settings->walletWebhookSecret($mode)
                : $settings->givingWebhookSecret($mode));

        return $secret === '' ? 'Webhook missing' : 'Webhook saved';
    }

    public function givingWebhookEndpoint(): string
    {
        return url('/api/giving/stripe/webhook');
    }

    public function eventWebhookEndpoint(): string
    {
        return url('/webhooks/event-installments/stripe');
    }

    public function walletWebhookEndpoint(): string
    {
        return url('/api/goshen-wallet/stripe/webhook');
    }

    private function fillFromSettings(StripePaymentSettings $settings): void
    {
        $this->mode = $settings->mode();
        $this->apiVersion = $settings->apiVersion();
        $this->givingSuccessUrl = $settings->givingSuccessUrl();
        $this->givingCancelUrl = $settings->givingCancelUrl();
        $this->eventSuccessUrl = $settings->eventSuccessUrl();
        $this->eventCancelUrl = $settings->eventCancelUrl();
        $this->walletSuccessUrl = $settings->walletSuccessUrl();
        $this->walletCancelUrl = $settings->walletCancelUrl();
        $this->testPublishableKey = $settings->publishableKey(StripePaymentSettings::MODE_TEST);
        $this->livePublishableKey = $settings->publishableKey(StripePaymentSettings::MODE_LIVE);
        $this->testSecretKey = '';
        $this->testWebhookSecret = '';
        $this->testGivingWebhookSecret = '';
        $this->testEventWebhookSecret = '';
        $this->testWalletWebhookSecret = '';
        $this->liveSecretKey = '';
        $this->liveWebhookSecret = '';
        $this->liveGivingWebhookSecret = '';
        $this->liveEventWebhookSecret = '';
        $this->liveWalletWebhookSecret = '';
    }

    private function payload(): array
    {
        return [
            'mode' => $this->mode,
            'api_version' => $this->apiVersion,
            'giving_success_url' => $this->givingSuccessUrl,
            'giving_cancel_url' => $this->givingCancelUrl,
            'event_success_url' => $this->eventSuccessUrl,
            'event_cancel_url' => $this->eventCancelUrl,
            'wallet_success_url' => $this->walletSuccessUrl,
            'wallet_cancel_url' => $this->walletCancelUrl,
            'test_publishable_key' => $this->testPublishableKey,
            'test_secret_key' => $this->testSecretKey,
            'test_webhook_secret' => $this->testWebhookSecret,
            'test_giving_webhook_secret' => $this->testGivingWebhookSecret,
            'test_event_webhook_secret' => $this->testEventWebhookSecret,
            'test_wallet_webhook_secret' => $this->testWalletWebhookSecret,
            'live_publishable_key' => $this->livePublishableKey,
            'live_secret_key' => $this->liveSecretKey,
            'live_webhook_secret' => $this->liveWebhookSecret,
            'live_giving_webhook_secret' => $this->liveGivingWebhookSecret,
            'live_event_webhook_secret' => $this->liveEventWebhookSecret,
            'live_wallet_webhook_secret' => $this->liveWalletWebhookSecret,
        ];
    }

    private function payloadWithFallbacks(StripePaymentSettings $settings): array
    {
        $payload = $this->payload();

        $fallbacks = [
            'api_version' => $settings->apiVersion(),
            'giving_success_url' => $settings->givingSuccessUrl(),
            'giving_cancel_url' => $settings->givingCancelUrl(),
            'event_success_url' => $settings->eventSuccessUrl(),
            'event_cancel_url' => $settings->eventCancelUrl(),
            'wallet_success_url' => $settings->walletSuccessUrl(),
            'wallet_cancel_url' => $settings->walletCancelUrl(),
        ];

        foreach ($fallbacks as $key => $fallback) {
            if (blank($payload[$key] ?? null)) {
                $payload[$key] = $fallback;
            }
        }

        return $payload;
    }

    private function checkoutUrlRules(): array
    {
        return [
            'required',
            'string',
            'max:2048',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $url = str_replace(
                    '{CHECKOUT_SESSION_ID}',
                    'cs_test_checkout_session_id',
                    trim((string) $value),
                );

                if (Validator::make(['url' => $url], ['url' => ['url']])->fails()) {
                    $fail('Please enter a full URL including https://.');
                }
            },
        ];
    }
}
