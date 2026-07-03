<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AppSettingResource;
use App\Filament\Resources\GoshenReferralPointEntryResource;
use App\Models\AppSetting;
use App\Support\AdminPermissions;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GoshenReferralSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?string $navigationLabel = 'Referral Settings';

    protected static ?string $title = 'Goshen Referral Settings';

    protected static ?string $slug = 'goshen-referral-settings';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.goshen-referral-settings';

    public bool $enabled = true;

    public int $pointsPerPaidRegistration = 1;

    public string $walletAmountPerPoint = '0';

    public int $minConvertiblePoints = 1;

    public function mount(): void
    {
        $this->enabled = filter_var(AppSetting::value('goshen_referrals_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->pointsPerPaidRegistration = max(1, (int) AppSetting::value('goshen_referral_points_per_paid_registration', 1));
        $this->walletAmountPerPoint = (string) AppSetting::value('goshen_referral_wallet_amount_per_point', '0');
        $this->minConvertiblePoints = max(1, (int) AppSetting::value('goshen_referral_min_convertible_points', 1));
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole('super_admin')
            || $user->can(AdminPermissions::resourcePermission(GoshenReferralPointEntryResource::class))
            || $user->can(AdminPermissions::resourcePermission(AppSettingResource::class))
        );
    }

    public function save(): void
    {
        $validated = validator([
            'enabled' => $this->enabled,
            'points_per_paid_registration' => $this->pointsPerPaidRegistration,
            'wallet_amount_per_point' => $this->walletAmountPerPoint,
            'min_convertible_points' => $this->minConvertiblePoints,
        ], [
            'enabled' => ['required', 'boolean'],
            'points_per_paid_registration' => ['required', 'integer', 'min:1', 'max:1000000'],
            'wallet_amount_per_point' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'min_convertible_points' => ['required', 'integer', 'min:1', 'max:1000000'],
        ])->validate();

        $this->saveSetting('goshen_referrals_enabled', $validated['enabled'] ? '1' : '0', 'Enable Goshen Retreat referral code capture, point validation, and wallet conversion.');
        $this->saveSetting('goshen_referral_points_per_paid_registration', (string) $validated['points_per_paid_registration'], 'Referral points awarded after a referred Goshen Retreat registration is paid.');
        $this->saveSetting('goshen_referral_wallet_amount_per_point', (string) round((float) $validated['wallet_amount_per_point'], 2), 'Wallet fund amount credited per validated referral point.');
        $this->saveSetting('goshen_referral_min_convertible_points', (string) $validated['min_convertible_points'], 'Minimum validated referral points a member must have before converting to wallet fund.');

        $this->mount();

        Notification::make()
            ->title('Referral settings saved')
            ->success()
            ->send();
    }

    private function saveSetting(string $key, string $value, string $description): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => 'goshen_referrals',
                'value' => $value,
                'is_secret' => false,
                'description' => $description,
            ],
        );
    }
}
