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

class GoogleFirebaseSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Google & Firebase';

    protected static ?string $title = 'Google & Firebase Settings';

    protected static ?string $slug = 'google-firebase-settings';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.google-firebase-settings';

    public bool $googleLoginEnabled = false;

    public string $googleWebClientId = '';

    public string $googleAndroidClientId = '';

    public string $googleIosClientId = '';

    public string $googleClientSecret = '';

    public string $androidPackageName = 'com.triumphant.android';

    public string $debugSha1 = '59:BF:49:56:FA:6B:CF:A9:6E:4D:01:08:AA:98:B1:81:D1:C4:0E:CD';

    public string $debugSha256 = 'FC:C9:DC:19:20:5B:6F:E1:97:EC:41:92:C8:7A:9F:25:63:10:3B:CF:F8:DB:FE:BA:D5:E8:A9:CC:25:84:19:38';

    public string $firebaseCredentialsPath = '';

    public string $googleApplicationCredentialsPath = '';

    public string $firebaseStorageBucket = '';

    public string $mobileFirebaseProjectId = 'mfm-triumphant-church-apps';

    public string $mobileFirebaseStorageBucket = 'mfm-triumphant-church-apps.firebasestorage.app';

    public string $webFirebaseAppId = '1:245162281677:web:cf1df7affcc5a4cb3eb784';

    public string $webFirebaseAuthDomain = 'mfm-triumphant-church-apps.firebaseapp.com';

    public string $webFirebaseApiKeyStatus = 'Configured';

    public array $requiredGoogleOrigins = [
        'https://goshen.shotfaz.com',
        'https://staging-goshen.shotfaz.com',
    ];

    public array $requiredFirebaseAuthDomains = [
        'goshen.shotfaz.com',
        'staging-goshen.shotfaz.com',
    ];

    public bool $firebaseCredentialsFileExists = false;

    public bool $firebaseCredentialsFileReadable = false;

    public bool $firebaseCredentialsMatchesMobileProject = false;

    public bool $firebaseStorageMatchesMobileProject = false;

    public string $firebaseCredentialsProjectId = '';

    public string $firebaseCredentialsClientEmail = '';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
        $this->googleLoginEnabled = filter_var(AppSetting::value('google_login_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
        $this->googleWebClientId = (string) AppSetting::value('google_web_client_id', '');
        $this->googleAndroidClientId = (string) AppSetting::value('google_android_client_id', '');
        $this->googleIosClientId = (string) AppSetting::value('google_ios_client_id', '');
        $this->googleClientSecret = '';

        $this->firebaseCredentialsPath = (string) (config('firebase.projects.app.credentials') ?: env('FIREBASE_CREDENTIALS') ?: '');
        $this->googleApplicationCredentialsPath = (string) (env('GOOGLE_APPLICATION_CREDENTIALS') ?: config('firebase.projects.app.credentials') ?: '');
        $this->firebaseStorageBucket = (string) (config('firebase.projects.app.storage.default_bucket') ?: env('FIREBASE_STORAGE_DEFAULT_BUCKET') ?: '');
        $this->webFirebaseAppId = (string) AppSetting::value('firebase_web_app_id', $this->webFirebaseAppId);
        $this->webFirebaseAuthDomain = (string) AppSetting::value('firebase_web_auth_domain', $this->webFirebaseAuthDomain);
        $this->webFirebaseApiKeyStatus = filled(AppSetting::value('firebase_web_api_key', '')) ? 'Configured in app settings' : 'Using Firebase project default';

        $this->inspectFirebaseCredentials();
    }

    public function save(): void
    {
        $validated = validator($this->payload(), [
            'google_login_enabled' => ['required', 'boolean'],
            'google_web_client_id' => ['nullable', 'string', 'max:255'],
            'google_android_client_id' => ['nullable', 'string', 'max:255'],
            'google_ios_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $this->saveSetting('google_login_enabled', $validated['google_login_enabled'] ? '1' : '0', false, 'Enable Google sign-in and registration in the web and mobile apps.');
        $this->saveSetting('google_web_client_id', (string) ($validated['google_web_client_id'] ?? ''), false, 'Google OAuth Web client ID used by the web portal and mobile app to request an ID token.');
        $this->saveSetting('google_android_client_id', (string) ($validated['google_android_client_id'] ?? ''), false, 'Google OAuth Android client ID for the app package and signing certificate.');
        $this->saveSetting('google_ios_client_id', (string) ($validated['google_ios_client_id'] ?? ''), false, 'Optional Google OAuth iOS client ID.');

        if (filled($validated['google_client_secret'] ?? '')) {
            $this->saveSetting('google_client_secret', (string) $validated['google_client_secret'], true, 'Optional Google OAuth client secret. Keep this private.');
        }

        $this->mount();

        Notification::make()
            ->title('Google & Firebase settings saved')
            ->body('The mobile app can now fetch the updated Google sign-in settings from the backend.')
            ->success()
            ->send();
    }

    private function payload(): array
    {
        return [
            'google_login_enabled' => $this->googleLoginEnabled,
            'google_web_client_id' => trim($this->googleWebClientId),
            'google_android_client_id' => trim($this->googleAndroidClientId),
            'google_ios_client_id' => trim($this->googleIosClientId),
            'google_client_secret' => trim($this->googleClientSecret),
        ];
    }

    private function saveSetting(string $key, string $value, bool $secret, string $description): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => 'auth',
                'value' => $value,
                'is_secret' => $secret,
                'description' => $description,
            ],
        );
    }

    private function inspectFirebaseCredentials(): void
    {
        $this->firebaseCredentialsFileExists = false;
        $this->firebaseCredentialsFileReadable = false;
        $this->firebaseCredentialsMatchesMobileProject = false;
        $this->firebaseStorageMatchesMobileProject = trim($this->firebaseStorageBucket) === $this->mobileFirebaseStorageBucket;
        $this->firebaseCredentialsProjectId = '';
        $this->firebaseCredentialsClientEmail = '';

        $resolvedPath = $this->resolveFirebaseCredentialPath($this->firebaseCredentialsPath);
        if ($resolvedPath === null || ! is_file($resolvedPath)) {
            return;
        }

        $this->firebaseCredentialsFileExists = true;
        if (! is_readable($resolvedPath)) {
            return;
        }

        $this->firebaseCredentialsFileReadable = true;
        $json = @file_get_contents($resolvedPath);
        if ($json === false) {
            $this->firebaseCredentialsFileReadable = false;
            return;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return;
        }

        $this->firebaseCredentialsProjectId = (string) ($decoded['project_id'] ?? '');
        $this->firebaseCredentialsClientEmail = (string) ($decoded['client_email'] ?? '');
        $this->firebaseCredentialsMatchesMobileProject = $this->firebaseCredentialsProjectId === $this->mobileFirebaseProjectId;
    }

    private function resolveFirebaseCredentialPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('/^(?:[A-Za-z]:[\/\\\\]|\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
