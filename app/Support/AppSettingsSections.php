<?php

namespace App\Support;

use App\Services\AdminAccessService;
use Illuminate\Support\Facades\Auth;

class AppSettingsSections
{
    public const HUB_PERMISSION = 'view_app_settings_hub';

    public static function sections(): array
    {
        return [
            'general' => ['label' => 'General', 'note' => 'Website, currency, media interval', 'icon' => 'heroicon-o-cog-6-tooth'],
            'branding' => ['label' => 'Branding', 'note' => 'Logo and app identity', 'icon' => 'heroicon-o-photo'],
            'social' => ['label' => 'Social links', 'note' => 'Public contact channels', 'icon' => 'heroicon-o-share'],
            'features' => ['label' => 'Feature activation', 'note' => 'Global feature switches', 'icon' => 'heroicon-o-adjustments-horizontal'],
            'performance' => ['label' => 'Performance', 'note' => 'Application cache control', 'icon' => 'heroicon-o-bolt'],
            'payments' => ['label' => 'Giving links', 'note' => 'Public giving link', 'icon' => 'heroicon-o-credit-card'],
            'support' => ['label' => 'Support', 'note' => 'Accommodation support contact', 'icon' => 'heroicon-o-lifebuoy'],
        ];
    }

    public static function permission(string $section): string
    {
        return 'manage_app_settings_'.$section;
    }

    public static function canManage(string $section): bool
    {
        return AdminAccessService::isSuperAdmin()
            || (isset(self::sections()[$section]) && (bool) Auth::user()?->can(self::permission($section)));
    }

    public static function fields(): array
    {
        return [
            'appName' => ['section' => 'branding', 'key' => 'app_name', 'group' => 'branding', 'rules' => ['nullable', 'string', 'max:190'], 'description' => 'Public app name used by mobile and web clients.'],
            'appLogo' => ['section' => 'branding', 'key' => 'app_logo', 'group' => 'branding', 'rules' => ['nullable', 'string', 'max:2048'], 'description' => 'Stored logo path used for app/admin branding.'],
            'websiteUrl' => ['section' => 'general', 'key' => 'website_url', 'group' => 'general', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public church website shown in the mobile app.'],
            'currency' => ['section' => 'general', 'key' => 'currency', 'group' => 'general', 'rules' => ['required', 'string', 'max:12'], 'description' => 'Default payment currency for public app transactions.'],
            'adsInterval' => ['section' => 'general', 'key' => 'ads_interval', 'group' => 'general', 'rules' => ['required', 'integer', 'min:0', 'max:3600'], 'description' => 'Interval in seconds used by app ad/media rotation.'],
            'facebookPage' => ['section' => 'social', 'key' => 'facebook_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public Facebook page URL.'],
            'youtubePage' => ['section' => 'social', 'key' => 'youtube_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public YouTube page URL.'],
            'tiktokPage' => ['section' => 'social', 'key' => 'tiktok_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public TikTok page URL.'],
            'instagramPage' => ['section' => 'social', 'key' => 'instagram_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public Instagram page URL.'],
            'telegramPage' => ['section' => 'social', 'key' => 'telegram_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public Telegram page URL.'],
            'mixlrPage' => ['section' => 'social', 'key' => 'mixlr_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public Mixlr page URL.'],
            'whatsappPage' => ['section' => 'social', 'key' => 'whatsapp_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public WhatsApp contact URL.'],
            'twitterPage' => ['section' => 'social', 'key' => 'twitter_page', 'group' => 'social', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Public X/Twitter page URL.'],
            'paypalLink' => ['section' => 'payments', 'key' => 'paypal_link', 'group' => 'payments', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Optional PayPal donation/payment URL.'],
            'testimoniesEnabled' => ['section' => 'features', 'key' => 'testimonies_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Turn the Testimonies & Thanksgiving Wall on or off.'],
            'counselingEnabled' => ['section' => 'features', 'key' => 'counseling_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Turn private Counseling requests and pastoral care chat on or off in the app and admin.'],
            'goshenRetreatEnabled' => ['section' => 'features', 'key' => 'goshen_retreat_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show or hide Goshen Retreat in the app.'],
            'goshenScannerEnabled' => ['section' => 'features', 'key' => 'goshen_scanner_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Allow authorized scanner users to access check-in features.'],
            'goshenWalletEnabled' => ['section' => 'features', 'key' => 'goshen_wallet_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Allow members to use Goshen wallet features.'],
            'goshenStripeGivingEnabled' => ['section' => 'features', 'key' => 'goshen_stripe_giving_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Allow Giving payments through Stripe.'],
            'fundraisingEnabled' => ['section' => 'features', 'key' => 'fundraising_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Project support/Fundraising features in the mobile and web apps.'],
            'prayerPointsEnabled' => ['section' => 'features', 'key' => 'prayer_points_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Prayer Points content in the mobile and web apps.'],
            'interactivePrayerWallEnabled' => ['section' => 'features', 'key' => 'interactive_prayer_wall_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show the Interactive Prayer Wall module.'],
            'hymnsEnabled' => ['section' => 'features', 'key' => 'hymns_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Hymns in the mobile app.'],
            'devotionalsEnabled' => ['section' => 'features', 'key' => 'devotionals_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Devotional content in the mobile app.'],
            'verseOfDayEnabled' => ['section' => 'features', 'key' => 'verse_of_day_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Verse of the Day in the mobile app.'],
            'transportationArrangementsEnabled' => ['section' => 'features', 'key' => 'transportation_arrangements_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show transportation arrangement information.'],
            'churchGroupsEnabled' => ['section' => 'features', 'key' => 'church_groups_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Church Groups and group requests.'],
            'dynamicFormsEnabled' => ['section' => 'features', 'key' => 'dynamic_forms_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show On-demand Forms in the mobile and web apps.'],
            'goshenQuizEnabled' => ['section' => 'features', 'key' => 'goshen_quiz_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Goshen Quiz in the mobile app.'],
            'goshenWalletWithdrawalsEnabled' => ['section' => 'features', 'key' => 'goshen_wallet_withdrawals_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Allow wallet withdrawal requests.'],
            'goshenWalletAutoTopupEnabled' => ['section' => 'features', 'key' => 'goshen_wallet_auto_topup_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Allow recurring wallet auto top-up plans.'],
            'goshenWalletAdminTopupEnabled' => ['section' => 'features', 'key' => 'goshen_wallet_admin_topup_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Allow authorized admins to add funds directly to member wallets from the admin panel.'],
            'branchesEnabled' => ['section' => 'features', 'key' => 'branches_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Show Branches module in the mobile app.'],
            'mobilePhoneOtpLoginEnabled' => ['section' => 'features', 'key' => 'mobile_phone_otp_login_enabled', 'group' => 'features', 'rules' => ['required', 'boolean'], 'description' => 'Allow Firebase phone OTP sign-in in the mobile app.'],
            'redisCacheEnabled' => ['section' => 'performance', 'key' => 'redis_cache_enabled', 'group' => 'performance', 'rules' => ['required', 'boolean'], 'description' => 'Use Redis for application cache only. Database cache remains available as the fallback; sessions, queues, payments, and wallet records are unchanged.'],
            'accommodationSupportName' => ['section' => 'support', 'key' => 'accommodation_booking_support_name', 'group' => 'support', 'rules' => ['nullable', 'string', 'max:120'], 'description' => 'Accommodation support contact name.'],
            'accommodationSupportEmail' => ['section' => 'support', 'key' => 'accommodation_booking_support_email', 'group' => 'support', 'rules' => ['nullable', 'email', 'max:190'], 'description' => 'Accommodation support email address.'],
            'accommodationSupportPhone' => ['section' => 'support', 'key' => 'accommodation_booking_support_phone', 'group' => 'support', 'rules' => ['nullable', 'string', 'max:80'], 'description' => 'Accommodation support phone number.'],
            'accommodationSupportWhatsapp' => ['section' => 'support', 'key' => 'accommodation_booking_support_whatsapp', 'group' => 'support', 'rules' => ['nullable', 'url', 'max:2048'], 'description' => 'Accommodation support WhatsApp URL.'],
            'accommodationSupportInstructions' => ['section' => 'support', 'key' => 'accommodation_booking_support_instructions', 'group' => 'support', 'rules' => ['nullable', 'string', 'max:5000'], 'description' => 'Accommodation booking support instructions.'],
        ];
    }
}
