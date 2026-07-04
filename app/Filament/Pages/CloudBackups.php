<?php

namespace App\Filament\Pages;

use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use BackedEnum;
use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Models\CloudBackupOAuthSetting;
use ChurchTools\CloudBackup\Models\CloudBackupRun;
use ChurchTools\CloudBackup\Models\CloudBackupSchedule;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CloudBackups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Cloud Backups';

    protected static ?string $title = 'Cloud Backups';

    protected static ?string $slug = 'cloud-backup-console';

    protected string $view = 'filament.pages.cloud-backups';

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
            || $user->can(AdminPermissions::CLOUD_BACKUPS)
        );
    }

    public function getViewData(): array
    {
        $activeProvider = CloudBackupOAuthSetting::activeProvider();

        return [
            'connections' => CloudBackupConnection::query()->latest()->get(),
            'activeConnections' => CloudBackupConnection::query()
                ->where('provider', $activeProvider)
                ->latest()
                ->get(),
            'schedules' => CloudBackupSchedule::query()->with('connection')->latest()->get(),
            'runs' => CloudBackupRun::query()
                ->with(['connection', 'schedule'])
                ->latest()
                ->limit(10)
                ->get(),
            'activeProvider' => $activeProvider,
            'oauthStatus' => [
                'google' => $this->oauthConfigured('google'),
                'onedrive' => $this->oauthConfigured('onedrive'),
            ],
            'oauthSettings' => [
                'google' => CloudBackupOAuthSetting::forProvider('google'),
                'onedrive' => CloudBackupOAuthSetting::forProvider('onedrive'),
            ],
            'oauthLinks' => [
                'google_console' => 'https://console.cloud.google.com/apis/credentials',
                'google_drive_api' => 'https://console.cloud.google.com/apis/library/drive.googleapis.com',
                'google_docs' => 'https://developers.google.com/workspace/guides/create-credentials',
                'microsoft_console' => 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
                'microsoft_docs' => 'https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app',
                'onedrive_docs' => 'https://learn.microsoft.com/en-us/onedrive/developer/rest-api/getting-started/authentication',
            ],
        ];
    }

    private function oauthConfigured(string $provider): bool
    {
        return CloudBackupOAuthSetting::configured($provider)
            || (filled(config("cloud-backup.oauth.{$provider}.client_id"))
                && filled(config("cloud-backup.oauth.{$provider}.client_secret")));
    }
}
