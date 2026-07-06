<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AddonResource;
use App\Filament\Resources\AiProviderSettingResource;
use App\Filament\Resources\AppSettingResource;
use App\Filament\Resources\RoleResource;
use App\Models\AppSetting;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AppSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'App Settings';

    protected static ?string $title = 'App Settings';

    protected static ?string $slug = 'app-settings-hub';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.app-settings';

    public string $websiteUrl = '';

    public string $appName = '';

    public string $appLogo = '';

    public string $currency = '£';

    public string $adsInterval = '0';

    public string $facebookPage = '';

    public string $youtubePage = '';

    public string $tiktokPage = '';

    public string $instagramPage = '';

    public string $telegramPage = '';

    public string $mixlrPage = '';

    public string $whatsappPage = '';

    public string $twitterPage = '';

    public string $paypalLink = '';

    public string $serviceAccountPath = '';

    public bool $googleLoginEnabled = false;

    public bool $testimoniesEnabled = false;

    public bool $goshenRetreatEnabled = true;

    public bool $goshenScannerEnabled = true;

    public bool $goshenWalletEnabled = true;

    public bool $goshenStripeGivingEnabled = true;

    public bool $goshenReferralsEnabled = true;

    public string $accommodationSupportName = '';

    public string $accommodationSupportEmail = '';

    public string $accommodationSupportPhone = '';

    public string $accommodationSupportWhatsapp = '';

    public string $accommodationSupportInstructions = '';

    /**
     * @var array<string, array{group: string, label: string, value: string, is_secret: bool, description: string|null}>
     */
    public array $additionalSettings = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && AdminMenuRegistry::visibleForPage(static::class);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole('super_admin')
            || $user->can(AdminPermissions::resourcePermission(AppSettingResource::class))
        );
    }

    public function mount(): void
    {
        $this->appName = (string) AppSetting::value('app_name', '');
        $this->appLogo = (string) AppSetting::value('app_logo', '');
        $this->websiteUrl = (string) AppSetting::value('website_url', '');
        $this->currency = (string) AppSetting::value('currency', '£');
        $this->adsInterval = (string) AppSetting::value('ads_interval', '0');

        $this->facebookPage = (string) AppSetting::value('facebook_page', '');
        $this->youtubePage = (string) AppSetting::value('youtube_page', '');
        $this->tiktokPage = (string) AppSetting::value('tiktok_page', '');
        $this->instagramPage = (string) AppSetting::value('instagram_page', '');
        $this->telegramPage = (string) AppSetting::value('telegram_page', '');
        $this->mixlrPage = (string) AppSetting::value('mixlr_page', '');
        $this->whatsappPage = (string) AppSetting::value('whatsapp_page', '');
        $this->twitterPage = (string) AppSetting::value('twitter_page', '');

        $this->paypalLink = (string) AppSetting::value('paypal_link', '');
        $this->serviceAccountPath = '';

        $this->googleLoginEnabled = filter_var(AppSetting::value('google_login_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
        $this->testimoniesEnabled = filter_var(AppSetting::value('testimonies_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
        $this->goshenRetreatEnabled = filter_var(AppSetting::value('goshen_retreat_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->goshenScannerEnabled = filter_var(AppSetting::value('goshen_scanner_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->goshenWalletEnabled = filter_var(AppSetting::value('goshen_wallet_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->goshenStripeGivingEnabled = filter_var(AppSetting::value('goshen_stripe_giving_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->goshenReferralsEnabled = filter_var(AppSetting::value('goshen_referrals_enabled', '1'), FILTER_VALIDATE_BOOLEAN);

        $this->accommodationSupportName = (string) AppSetting::value('accommodation_booking_support_name', '');
        $this->accommodationSupportEmail = (string) AppSetting::value('accommodation_booking_support_email', '');
        $this->accommodationSupportPhone = (string) AppSetting::value('accommodation_booking_support_phone', '');
        $this->accommodationSupportWhatsapp = (string) AppSetting::value('accommodation_booking_support_whatsapp', '');
        $this->accommodationSupportInstructions = (string) AppSetting::value('accommodation_booking_support_instructions', '');
        $this->additionalSettings = $this->loadAdditionalSettings();
    }

    public function getViewData(): array
    {
        return [
            'quickLinks' => [
                [
                    'label' => 'Payment Gateways',
                    'description' => 'Stripe test/live keys, webhooks, and checkout URLs.',
                    'url' => PaymentGateways::getUrl(),
                ],
                [
                    'label' => 'Google & Firebase',
                    'description' => 'Google login IDs, fingerprints, and Firebase Admin status.',
                    'url' => GoogleFirebaseSettings::getUrl(),
                ],
                [
                    'label' => 'Referral Settings',
                    'description' => 'Referral points, wallet conversion rate, and conversion minimums.',
                    'url' => GoshenReferralSettings::getUrl(),
                ],
                [
                    'label' => 'Cloud Backups',
                    'description' => 'Google Drive and OneDrive backup providers.',
                    'url' => CloudBackups::getUrl(),
                ],
                [
                    'label' => 'AI Providers',
                    'description' => 'AI provider model, API key, and test configuration.',
                    'url' => AiProviderSettingResource::getUrl('index'),
                ],
                [
                    'label' => 'Add-ons',
                    'description' => 'Installed add-ons, lifecycle status, and package health.',
                    'url' => AddonResource::getUrl('index'),
                ],
                [
                    'label' => 'Role Permissions',
                    'description' => 'Admin roles and feature permissions.',
                    'url' => RoleResource::getUrl('index'),
                ],
                [
                    'label' => 'Admin Menu Settings',
                    'description' => 'Role-based visibility for admin navigation items.',
                    'url' => AdminMenuSettings::getUrl(),
                ],
            ],
            'additionalSettingGroups' => $this->additionalSettingGroups(),
        ];
    }

    public function save(): void
    {
        $validated = validator($this->payload(), [
            'app_name' => ['nullable', 'string', 'max:190'],
            'app_logo' => ['nullable', 'string', 'max:2048'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'currency' => ['required', 'string', 'max:12'],
            'ads_interval' => ['required', 'integer', 'min:0', 'max:3600'],
            'facebook_page' => ['nullable', 'url', 'max:2048'],
            'youtube_page' => ['nullable', 'url', 'max:2048'],
            'tiktok_page' => ['nullable', 'url', 'max:2048'],
            'instagram_page' => ['nullable', 'url', 'max:2048'],
            'telegram_page' => ['nullable', 'url', 'max:2048'],
            'mixlr_page' => ['nullable', 'url', 'max:2048'],
            'whatsapp_page' => ['nullable', 'url', 'max:2048'],
            'twitter_page' => ['nullable', 'url', 'max:2048'],
            'paypal_link' => ['nullable', 'url', 'max:2048'],
            'service_account_path' => ['nullable', 'string', 'max:2048'],
            'google_login_enabled' => ['required', 'boolean'],
            'testimonies_enabled' => ['required', 'boolean'],
            'goshen_retreat_enabled' => ['required', 'boolean'],
            'goshen_scanner_enabled' => ['required', 'boolean'],
            'goshen_wallet_enabled' => ['required', 'boolean'],
            'goshen_stripe_giving_enabled' => ['required', 'boolean'],
            'goshen_referrals_enabled' => ['required', 'boolean'],
            'accommodation_booking_support_name' => ['nullable', 'string', 'max:120'],
            'accommodation_booking_support_email' => ['nullable', 'email', 'max:190'],
            'accommodation_booking_support_phone' => ['nullable', 'string', 'max:80'],
            'accommodation_booking_support_whatsapp' => ['nullable', 'url', 'max:2048'],
            'accommodation_booking_support_instructions' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $validated['currency'] = trim((string) $validated['currency']);

        foreach ($this->settingDefinitions($validated) as $definition) {
            $this->saveSetting(...$definition);
        }

        $this->saveAdditionalSettings();
        $this->mount();

        Notification::make()
            ->title('App settings saved')
            ->success()
            ->send();
    }

    private function payload(): array
    {
        return [
            'app_name' => $this->appName,
            'app_logo' => $this->appLogo,
            'website_url' => $this->websiteUrl,
            'currency' => $this->currency,
            'ads_interval' => $this->adsInterval,
            'facebook_page' => $this->facebookPage,
            'youtube_page' => $this->youtubePage,
            'tiktok_page' => $this->tiktokPage,
            'instagram_page' => $this->instagramPage,
            'telegram_page' => $this->telegramPage,
            'mixlr_page' => $this->mixlrPage,
            'whatsapp_page' => $this->whatsappPage,
            'twitter_page' => $this->twitterPage,
            'paypal_link' => $this->paypalLink,
            'service_account_path' => $this->serviceAccountPath,
            'google_login_enabled' => $this->googleLoginEnabled,
            'testimonies_enabled' => $this->testimoniesEnabled,
            'goshen_retreat_enabled' => $this->goshenRetreatEnabled,
            'goshen_scanner_enabled' => $this->goshenScannerEnabled,
            'goshen_wallet_enabled' => $this->goshenWalletEnabled,
            'goshen_stripe_giving_enabled' => $this->goshenStripeGivingEnabled,
            'goshen_referrals_enabled' => $this->goshenReferralsEnabled,
            'accommodation_booking_support_name' => $this->accommodationSupportName,
            'accommodation_booking_support_email' => $this->accommodationSupportEmail,
            'accommodation_booking_support_phone' => $this->accommodationSupportPhone,
            'accommodation_booking_support_whatsapp' => $this->accommodationSupportWhatsapp,
            'accommodation_booking_support_instructions' => $this->accommodationSupportInstructions,
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed, 3: string}>
     */
    private function settingDefinitions(array $values): array
    {
        return [
            ['branding', 'app_name', $values['app_name'] ?? '', 'Public app name used by mobile and web clients.'],
            ['branding', 'app_logo', $values['app_logo'] ?? '', 'Stored logo path used for app/admin branding.'],
            ['general', 'website_url', $values['website_url'] ?? '', 'Public church website shown in the mobile app.'],
            ['general', 'currency', $values['currency'], 'Default payment currency for public app transactions.'],
            ['general', 'ads_interval', (string) $values['ads_interval'], 'Interval in seconds used by app ad/media rotation.'],
            ['social', 'facebook_page', $values['facebook_page'] ?? '', 'Public Facebook page URL.'],
            ['social', 'youtube_page', $values['youtube_page'] ?? '', 'Public YouTube page URL.'],
            ['social', 'tiktok_page', $values['tiktok_page'] ?? '', 'Public TikTok page URL.'],
            ['social', 'instagram_page', $values['instagram_page'] ?? '', 'Public Instagram page URL.'],
            ['social', 'telegram_page', $values['telegram_page'] ?? '', 'Public Telegram page URL.'],
            ['social', 'mixlr_page', $values['mixlr_page'] ?? '', 'Public Mixlr page URL.'],
            ['social', 'whatsapp_page', $values['whatsapp_page'] ?? '', 'Public WhatsApp contact URL.'],
            ['social', 'twitter_page', $values['twitter_page'] ?? '', 'Public X/Twitter page URL.'],
            ['payments', 'paypal_link', $values['paypal_link'] ?? '', 'Optional PayPal donation/payment URL.'],
            ['firebase', 'service_account_path', $values['service_account_path'] ?? '', 'Legacy Firebase service account path. Prefer server environment credentials for production.', true],
            ['features', 'google_login_enabled', $values['google_login_enabled'] ? '1' : '0', 'Turn on Google sign-in and registration in the Flutter app.'],
            ['features', 'testimonies_enabled', $values['testimonies_enabled'] ? '1' : '0', 'Turn the Testimonies & Thanksgiving Wall on or off.'],
            ['features', 'goshen_retreat_enabled', $values['goshen_retreat_enabled'] ? '1' : '0', 'Show or hide Goshen Retreat in the app.'],
            ['features', 'goshen_scanner_enabled', $values['goshen_scanner_enabled'] ? '1' : '0', 'Allow authorized scanner users to access check-in features.'],
            ['features', 'goshen_wallet_enabled', $values['goshen_wallet_enabled'] ? '1' : '0', 'Allow members to use Goshen wallet features.'],
            ['features', 'goshen_stripe_giving_enabled', $values['goshen_stripe_giving_enabled'] ? '1' : '0', 'Allow Giving payments through Stripe.'],
            ['features', 'goshen_referrals_enabled', $values['goshen_referrals_enabled'] ? '1' : '0', 'Allow referral code entry and wallet conversion.'],
            ['support', 'accommodation_booking_support_name', $values['accommodation_booking_support_name'] ?? '', 'Accommodation support contact name.'],
            ['support', 'accommodation_booking_support_email', $values['accommodation_booking_support_email'] ?? '', 'Accommodation support email address.'],
            ['support', 'accommodation_booking_support_phone', $values['accommodation_booking_support_phone'] ?? '', 'Accommodation support phone number.'],
            ['support', 'accommodation_booking_support_whatsapp', $values['accommodation_booking_support_whatsapp'] ?? '', 'Accommodation support WhatsApp URL.'],
            ['support', 'accommodation_booking_support_instructions', $values['accommodation_booking_support_instructions'] ?? '', 'Accommodation booking support instructions.'],
        ];
    }

    private function saveSetting(string $group, string $key, mixed $value, string $description, bool $isSecret = false): void
    {
        if ($isSecret && blank($value) && AppSetting::query()->where('key', $key)->exists()) {
            AppSetting::query()
                ->where('key', $key)
                ->update([
                    'group' => $group,
                    'is_secret' => true,
                    'description' => $description,
                ]);

            return;
        }

        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => (string) ($value ?? ''),
                'is_secret' => $isSecret,
                'description' => $description,
            ],
        );
    }

    /**
     * @return array<string, array{group: string, label: string, value: string, is_secret: bool, description: string|null}>
     */
    private function loadAdditionalSettings(): array
    {
        return AppSetting::query()
            ->whereNotIn('key', array_merge($this->managedSettingKeys(), $this->linkedSettingKeys()))
            ->orderBy('group')
            ->orderBy('key')
            ->get(['group', 'key', 'value', 'is_secret', 'description'])
            ->mapWithKeys(fn (AppSetting $setting): array => [
                $setting->key => [
                    'group' => (string) ($setting->group ?: 'Other'),
                    'label' => str((string) $setting->key)->replace('_', ' ')->headline()->toString(),
                    'value' => $setting->is_secret ? '' : (string) ($setting->value ?? ''),
                    'is_secret' => (bool) $setting->is_secret,
                    'description' => $setting->description,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, array{key: string, label: string, value: string, is_secret: bool, description: string|null}>>
     */
    public function additionalSettingGroups(): array
    {
        $groups = [];

        foreach ($this->additionalSettings as $key => $setting) {
            $group = str((string) ($setting['group'] ?? 'Other'))
                ->replace(['_', '-'], ' ')
                ->headline()
                ->toString();

            $groups[$group][] = [
                'key' => (string) $key,
                'label' => (string) ($setting['label'] ?? $key),
                'value' => (string) ($setting['value'] ?? ''),
                'is_secret' => (bool) ($setting['is_secret'] ?? false),
                'description' => $setting['description'] ?? null,
            ];
        }

        ksort($groups);

        return $groups;
    }

    private function saveAdditionalSettings(): void
    {
        foreach ($this->additionalSettings as $key => $setting) {
            $record = AppSetting::query()->where('key', $key)->first();

            if (! $record) {
                continue;
            }

            $value = (string) ($setting['value'] ?? '');

            if ($record->is_secret && $value === '') {
                continue;
            }

            $record->forceFill([
                'value' => $value,
            ])->save();
        }
    }

    /**
     * @return array<int, string>
     */
    private function managedSettingKeys(): array
    {
        return [
            'app_name',
            'app_logo',
            'website_url',
            'currency',
            'ads_interval',
            'facebook_page',
            'youtube_page',
            'tiktok_page',
            'instagram_page',
            'telegram_page',
            'mixlr_page',
            'whatsapp_page',
            'twitter_page',
            'paypal_link',
            'service_account_path',
            'google_login_enabled',
            'testimonies_enabled',
            'goshen_retreat_enabled',
            'goshen_scanner_enabled',
            'goshen_wallet_enabled',
            'goshen_stripe_giving_enabled',
            'goshen_referrals_enabled',
            'accommodation_booking_support_name',
            'accommodation_booking_support_email',
            'accommodation_booking_support_phone',
            'accommodation_booking_support_whatsapp',
            'accommodation_booking_support_instructions',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function linkedSettingKeys(): array
    {
        return [
            'google_android_client_id',
            'google_client_secret',
            'google_ios_client_id',
            'google_web_client_id',
            'goshen_referral_min_convertible_points',
            'goshen_referral_points_per_paid_registration',
            'goshen_referral_wallet_amount_per_point',
            'stripe_api_version',
            'stripe_event_cancel_url',
            'stripe_event_success_url',
            'stripe_giving_cancel_url',
            'stripe_giving_success_url',
            'stripe_live_event_webhook_secret',
            'stripe_live_giving_webhook_secret',
            'stripe_live_publishable_key',
            'stripe_live_secret_key',
            'stripe_live_wallet_webhook_secret',
            'stripe_live_webhook_secret',
            'stripe_mode',
            'stripe_test_event_webhook_secret',
            'stripe_test_giving_webhook_secret',
            'stripe_test_publishable_key',
            'stripe_test_secret_key',
            'stripe_test_wallet_webhook_secret',
            'stripe_test_webhook_secret',
            'stripe_wallet_cancel_url',
            'stripe_wallet_success_url',
        ];
    }
}
