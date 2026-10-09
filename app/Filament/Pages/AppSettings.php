<?php

namespace App\Filament\Pages;

use App\Support\AppSettingsSections;
use App\Services\AdminAccessService;
use Illuminate\Support\Facades\DB;
use App\Models\AppSetting;
use App\Support\AdminMenuRegistry;
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

    public bool $testimoniesEnabled = false;

    public bool $counselingEnabled = true;

    public bool $goshenRetreatEnabled = true;

    public bool $goshenScannerEnabled = true;

    public bool $goshenWalletEnabled = true;

    public bool $goshenStripeGivingEnabled = true;

    public bool $fundraisingEnabled = true;

    public bool $prayerPointsEnabled = true;

    public bool $interactivePrayerWallEnabled = true;

    public bool $hymnsEnabled = true;

    public bool $devotionalsEnabled = true;

    public bool $verseOfDayEnabled = true;

    public bool $transportationArrangementsEnabled = true;

    public bool $churchGroupsEnabled = true;

    public bool $dynamicFormsEnabled = true;

    public bool $goshenQuizEnabled = true;

    public bool $goshenWalletWithdrawalsEnabled = true;

    public bool $goshenWalletAutoTopupEnabled = true;

    public bool $goshenWalletAdminTopupEnabled = true;

    public bool $branchesEnabled = true;

    public bool $mobilePhoneOtpLoginEnabled = true;

    public bool $redisCacheEnabled = false;

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
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return AdminAccessService::isSuperAdmin()
            || (bool) Auth::user()?->can(AppSettingsSections::HUB_PERMISSION);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->reset(array_keys(AppSettingsSections::fields()));
        foreach (AppSettingsSections::fields() as $property => $field) {
            if (! AppSettingsSections::canManage($field['section'])) {
                continue;
            }
            $setting = AppSetting::where('key', $field['key'])->first();
            if ($setting?->is_secret) {
                continue;
            }
            $value = $setting?->value ?? $this->{$property};
            $this->{$property} = is_bool($this->{$property})
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : (string) $value;
        }
        $this->additionalSettings = AdminAccessService::isSuperAdmin() ? $this->loadAdditionalSettings() : [];
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);
        foreach (AppSettingsSections::fields() as $property => $field) {
            if (! AppSettingsSections::canManage($field['section'])) {
                $this->reset($property);
            }
        }
        if (! AdminAccessService::isSuperAdmin()) {
            $this->additionalSettings = [];
        }
    }

    public function updating(string $property): void
    {
        if (str_starts_with($property, 'additionalSettings')) {
            abort_unless(AdminAccessService::isSuperAdmin(), 403);
            return;
        }
        $field = AppSettingsSections::fields()[$property] ?? null;
        abort_unless($field && AppSettingsSections::canManage($field['section']), 403);
    }

    public function getViewData(): array
    {
        $sections = array_filter(AppSettingsSections::sections(), fn ($key) => AppSettingsSections::canManage($key), ARRAY_FILTER_USE_KEY);
        $sections['integrations'] = ['label' => 'Settings pages', 'note' => 'Pages you can access', 'icon' => 'heroicon-o-squares-plus'];
        if (AdminAccessService::isSuperAdmin()) {
            $sections['other'] = ['label' => 'Other settings', 'note' => 'Super Admin maintenance', 'icon' => 'heroicon-o-ellipsis-horizontal-circle'];
        }
        return ['sections' => $sections, 'quickLinks' => AdminMenuRegistry::settingsQuickLinks(),
            'additionalSettingGroups' => AdminAccessService::isSuperAdmin() ? $this->additionalSettingGroups() : []];
    }

    public function save(string $section): void
    {
        abort_unless(static::canAccess() && AppSettingsSections::canManage($section), 403);
        abort_unless(isset(AppSettingsSections::sections()[$section]) || $section === 'other', 403);
        $this->resetValidation();
        DB::transaction(function () use ($section): void {
            if ($section === 'other') {
                $this->saveAdditionalSettings();
                return;
            }
            $fields = array_filter(AppSettingsSections::fields(), fn ($field) => $field['section'] === $section);
            $payload = $rules = [];
            foreach ($fields as $property => $field) {
                abort_if(AppSetting::where('key', $field['key'])->where('is_secret', true)->exists(), 403,
                    'Secret settings must be changed through System Settings Maintenance.');
                $payload[$property] = $this->{$property};
                $rules[$property] = $field['rules'];
            }
            $validated = validator($payload, $rules)->validate();
            foreach ($fields as $property => $field) {
                $value = $validated[$property];
                AppSetting::updateOrCreate(['key' => $field['key']], [
                    'group' => $field['group'], 'description' => $field['description'], 'is_secret' => false,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : trim((string) $value),
                ]);
            }
        });
        $this->mount();
        Notification::make()->title('Settings section saved')->success()->send();
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
        abort_unless(AdminAccessService::isSuperAdmin(), 403);
        validator($this->additionalSettings, ['*' => ['array'], '*.value' => ['nullable', 'string', 'max:65535']])->validate();
        $allowed = $this->loadAdditionalSettings();
        abort_if(array_diff(array_keys($this->additionalSettings), array_keys($allowed)) !== [], 403);
        foreach ($this->additionalSettings as $key => $setting) {
            $record = AppSetting::where('key', $key)->lockForUpdate()->firstOrFail();
            $value = (string) ($setting['value'] ?? '');
            if ($record->is_secret && $value === '') {
                continue;
            }
            $record->update(['value' => $value]);
        }
    }

    private function managedSettingKeys(): array
    {
        return array_column(AppSettingsSections::fields(), 'key');
    }

    /**
     * @return array<int, string>
     */
    private function linkedSettingKeys(): array
    {
        return [
            'google_login_enabled',
            'goshen_referrals_enabled',
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
