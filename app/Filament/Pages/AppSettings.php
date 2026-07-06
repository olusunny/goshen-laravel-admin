<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AppSettingResource;
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

    public string $currency = 'GBP';

    public string $adsInterval = '0';

    public string $facebookPage = '';

    public string $youtubePage = '';

    public string $tiktokPage = '';

    public string $instagramPage = '';

    public string $telegramPage = '';

    public string $mixlrPage = '';

    public string $whatsappPage = '';

    public string $twitterPage = '';

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
        $this->websiteUrl = (string) AppSetting::value('website_url', '');
        $this->currency = strtoupper((string) AppSetting::value('currency', 'GBP'));
        $this->adsInterval = (string) AppSetting::value('ads_interval', '0');

        $this->facebookPage = (string) AppSetting::value('facebook_page', '');
        $this->youtubePage = (string) AppSetting::value('youtube_page', '');
        $this->tiktokPage = (string) AppSetting::value('tiktok_page', '');
        $this->instagramPage = (string) AppSetting::value('instagram_page', '');
        $this->telegramPage = (string) AppSetting::value('telegram_page', '');
        $this->mixlrPage = (string) AppSetting::value('mixlr_page', '');
        $this->whatsappPage = (string) AppSetting::value('whatsapp_page', '');
        $this->twitterPage = (string) AppSetting::value('twitter_page', '');

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
                    'label' => 'Cloud Backups',
                    'description' => 'Google Drive and OneDrive backup providers.',
                    'url' => CloudBackups::getUrl(),
                ],
                [
                    'label' => 'Advanced Settings',
                    'description' => 'Low-level key/value records for uncommon settings.',
                    'url' => AppSettingResource::getUrl('index'),
                ],
            ],
        ];
    }

    public function save(): void
    {
        $validated = validator($this->payload(), [
            'website_url' => ['nullable', 'url', 'max:2048'],
            'currency' => ['required', 'string', 'size:3'],
            'ads_interval' => ['required', 'integer', 'min:0', 'max:3600'],
            'facebook_page' => ['nullable', 'url', 'max:2048'],
            'youtube_page' => ['nullable', 'url', 'max:2048'],
            'tiktok_page' => ['nullable', 'url', 'max:2048'],
            'instagram_page' => ['nullable', 'url', 'max:2048'],
            'telegram_page' => ['nullable', 'url', 'max:2048'],
            'mixlr_page' => ['nullable', 'url', 'max:2048'],
            'whatsapp_page' => ['nullable', 'url', 'max:2048'],
            'twitter_page' => ['nullable', 'url', 'max:2048'],
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

        $validated['currency'] = strtoupper($validated['currency']);

        foreach ($this->settingDefinitions($validated) as $definition) {
            $this->saveSetting(...$definition);
        }

        $this->mount();

        Notification::make()
            ->title('App settings saved')
            ->success()
            ->send();
    }

    private function payload(): array
    {
        return [
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

    private function saveSetting(string $group, string $key, mixed $value, string $description): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => (string) ($value ?? ''),
                'is_secret' => false,
                'description' => $description,
            ],
        );
    }
}
