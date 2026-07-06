<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSettingResource\Pages;
use App\Models\AppSetting;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AppSettingResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AppSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Advanced Settings';

    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('App setting sections')
                    ->extraAttributes(['class' => 'goshen-settings-tabs'])
                    ->vertical()
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('group')
                                    ->required(),
                                Forms\Components\TextInput::make('key')
                                    ->required(),
                                Forms\Components\Textarea::make('description')
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Value')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Forms\Components\FileUpload::make('logo_value')
                                    ->label('Logo image')
                                    ->disk('public')
                                    ->directory('branding')
                                    ->image()
                                    ->imageEditor()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('512')
                                    ->imageResizeTargetHeight('160')
                                    ->maxSize(4096)
                                    ->downloadable()
                                    ->previewable()
                                    ->visible(fn ($get): bool => $get('key') === 'app_logo')
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('text_value')
                                    ->label('Value')
                                    ->hidden(fn ($get): bool => $get('key') === 'app_logo' || in_array($get('key'), self::urlKeys(), true) || self::isToggleKey($get('key')))
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('boolean_value')
                                    ->label(fn ($get): string => self::friendlyLabel($get('key')))
                                    ->helperText(fn ($get): ?string => self::helperText($get('key')))
                                    ->visible(fn ($get): bool => self::isToggleKey($get('key')))
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('url_value')
                                    ->label(fn ($get): string => self::friendlyLabel($get('key')))
                                    ->url()
                                    ->helperText(fn ($get): ?string => self::helperText($get('key')))
                                    ->visible(fn ($get): bool => in_array($get('key'), self::urlKeys(), true))
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Security')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Advanced system options')
                                    ->description('Use these controls only for credentials and private configuration values.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_secret')
                                            ->label('Treat value as secret')
                                            ->helperText('Enable this for API keys, SMTP passwords, Firebase credentials, AI keys, and other private settings.')
                                            ->required(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => self::friendlyLabel($state))
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('value')
                    ->label('Logo')
                    ->disk('public')
                    ->height(36)
                    ->visibleFrom('lg')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_secret')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppSettings::route('/'),
            'create' => Pages\CreateAppSetting::route('/create'),
            'edit' => Pages\EditAppSetting::route('/{record}/edit'),
        ];
    }

    private static function urlKeys(): array
    {
        return [
            'website_url',
            'facebook_page',
            'youtube_page',
            'tiktok_page',
            'instagram_page',
            'telegram_page',
            'mixlr_page',
            'whatsapp_page',
            'twitter_page',
        ];
    }

    public static function isToggleKey(?string $key): bool
    {
        return in_array($key, self::toggleKeys(), true);
    }

    public static function isUrlKey(?string $key): bool
    {
        return in_array($key, self::urlKeys(), true);
    }

    public static function prepareVirtualValueFields(array $data): array
    {
        $key = $data['key'] ?? null;

        $data['logo_value'] = $key === 'app_logo' ? ($data['value'] ?? null) : null;
        $data['boolean_value'] = self::isToggleKey($key)
            ? filter_var($data['value'] ?? false, FILTER_VALIDATE_BOOLEAN)
            : false;
        $data['url_value'] = self::isUrlKey($key) ? ($data['value'] ?? null) : null;
        $data['text_value'] = $key !== 'app_logo' && ! self::isToggleKey($key) && ! self::isUrlKey($key)
            ? ($data['value'] ?? null)
            : null;

        return $data;
    }

    public static function collapseVirtualValueFields(array $data): array
    {
        $key = $data['key'] ?? null;

        if ($key === 'app_logo') {
            $data['value'] = $data['logo_value'] ?? null;
        } elseif (self::isToggleKey($key)) {
            $data['value'] = filter_var($data['boolean_value'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        } elseif (self::isUrlKey($key)) {
            $data['value'] = $data['url_value'] ?? '';
        } else {
            $data['value'] = $data['text_value'] ?? '';
        }

        unset($data['logo_value'], $data['boolean_value'], $data['url_value'], $data['text_value']);

        return $data;
    }

    private static function toggleKeys(): array
    {
        return [
            'google_login_enabled',
            'testimonies_enabled',
            'goshen_retreat_enabled',
            'goshen_scanner_enabled',
            'goshen_wallet_enabled',
            'goshen_stripe_giving_enabled',
            'goshen_referrals_enabled',
        ];
    }

    private static function friendlyLabel(?string $key): string
    {
        return match ($key) {
            'website_url' => 'Website URL',
            'facebook_page' => 'Facebook URL',
            'youtube_page' => 'YouTube URL',
            'tiktok_page' => 'TikTok URL',
            'instagram_page' => 'Instagram URL',
            'telegram_page' => 'Telegram URL',
            'mixlr_page' => 'Mixlr URL',
            'whatsapp_page' => 'WhatsApp URL',
            'twitter_page' => 'X/Twitter URL',
            'google_login_enabled' => 'Enable Google login',
            'testimonies_enabled' => 'Enable Testimonies & Thanksgiving Wall',
            'goshen_retreat_enabled' => 'Enable Goshen Retreat',
            'goshen_scanner_enabled' => 'Enable Goshen scanner',
            'goshen_wallet_enabled' => 'Enable Goshen wallet',
            'goshen_stripe_giving_enabled' => 'Enable Goshen Stripe giving',
            'goshen_referrals_enabled' => 'Enable Goshen referrals',
            'goshen_referral_points_per_paid_registration' => 'Goshen referral points per paid registration',
            'goshen_referral_wallet_amount_per_point' => 'Goshen referral wallet amount per point',
            'goshen_referral_min_convertible_points' => 'Goshen referral minimum conversion points',
            'google_web_client_id' => 'Google Web client ID',
            'google_android_client_id' => 'Google Android client ID',
            'google_ios_client_id' => 'Google iOS client ID',
            'google_client_secret' => 'Google client secret',
            'accommodation_booking_support_name' => 'Accommodation support name',
            'accommodation_booking_support_email' => 'Accommodation support email',
            'accommodation_booking_support_phone' => 'Accommodation support phone',
            'accommodation_booking_support_whatsapp' => 'Accommodation support WhatsApp',
            'accommodation_booking_support_instructions' => 'Accommodation support instructions',
            default => str((string) $key)->replace('_', ' ')->title()->toString(),
        };
    }

    private static function helperText(?string $key): ?string
    {
        return match ($key) {
            'website_url' => 'Public church website shown in the mobile app.',
            'whatsapp_page' => 'Use a full WhatsApp link such as https://wa.me/234...',
            'google_login_enabled' => 'Turn on native Google sign-in and registration in the Flutter app.',
            'testimonies_enabled' => 'Turn the Testimonies & Thanksgiving Wall on or off in the mobile app.',
            'goshen_retreat_enabled' => 'Show or hide the Goshen Retreat module in the mobile app and web experience.',
            'goshen_scanner_enabled' => 'Allow authorized event scanner users to access check-in features.',
            'goshen_wallet_enabled' => 'Allow members to use Goshen wallet features in the mobile app.',
            'goshen_stripe_giving_enabled' => 'Allow new Goshen giving payments through the configured Stripe gateway.',
            'goshen_referrals_enabled' => 'Allow referral code entry, point validation, and wallet conversion for Goshen Retreat.',
            'goshen_referral_points_per_paid_registration' => 'Awarded after a referred Goshen Retreat registration is paid.',
            'goshen_referral_wallet_amount_per_point' => 'Wallet fund credited for each validated referral point.',
            'goshen_referral_min_convertible_points' => 'Minimum validated points required before a member can convert.',
            'google_web_client_id' => 'Public OAuth Web client ID. The mobile app uses this as serverClientId to receive a Google ID token.',
            'google_android_client_id' => 'Public OAuth Android client ID for the package name and app signing certificate.',
            'google_ios_client_id' => 'Optional public OAuth iOS client ID.',
            'google_client_secret' => 'Optional secret credential. Do not expose this in the mobile app.',
            'accommodation_booking_support_name',
            'accommodation_booking_support_email',
            'accommodation_booking_support_phone',
            'accommodation_booking_support_whatsapp',
            'accommodation_booking_support_instructions' => 'Shown in booking, payment, receipt, cancellation, and checkout reminder messages.',
            default => in_array($key, self::urlKeys(), true)
                ? 'Use the full public URL, including https://'
                : null,
        };
    }
}
