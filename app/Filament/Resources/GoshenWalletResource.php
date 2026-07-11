<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenWalletResource\Pages;
use App\Models\AppSetting;
use App\Models\GoshenWallet;
use App\Models\User;
use App\Services\GoshenWalletService;
use App\Services\WalletSecurityResetService;
use App\Support\AdminPermissions;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class GoshenWalletResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenWallet::class;

    protected static ?string $modelLabel = 'Goshen wallet';

    protected static ?string $pluralModelLabel = 'Goshen wallets';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?int $navigationSort = 42;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Wallet owner')
                ->columns(3)
                ->schema([
                    TextEntry::make('user.name')->label('Name')->placeholder('No name'),
                    TextEntry::make('user.email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('user.phone')->label('Phone')->copyable()->placeholder('No phone'),
                    TextEntry::make('user.country_of_residence')->label('Country')->placeholder('Not set'),
                    TextEntry::make('user.state_county_province')->label('State / county / province')->placeholder('Not set'),
                    TextEntry::make('created_at')->label('Wallet opened')->dateTime(),
                ]),
            Section::make('Savings')
                ->columns(4)
                ->schema([
                    TextEntry::make('balance')->money(fn (GoshenWallet $record): string => $record->currency ?: 'GBP'),
                    TextEntry::make('goal_amount')->label('Goal')->money(fn (GoshenWallet $record): string => $record->currency ?: 'GBP')->placeholder('No goal set'),
                    TextEntry::make('goal_label')->label('Goal label')->placeholder('No label'),
                    TextEntry::make('goal_target_at')->label('Goal target date')->dateTime()->placeholder('No target date'),
                    TextEntry::make('stripe_customer_id')->label('Stripe customer')->copyable()->placeholder('No saved customer'),
                    TextEntry::make('stripe_payment_method_id')->label('Saved payment method')->copyable()->placeholder('No saved card'),
                    TextEntry::make('updated_at')->label('Last updated')->dateTime(),
                ]),
            Section::make('Wallet security support')
                ->columns(3)
                ->schema([
                    TextEntry::make('wallet_security_state')
                        ->label('Reset status')
                        ->state(fn (GoshenWallet $record): string => $record->user?->wallet_security_reset_required ? 'Reset pending' : 'Ready')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Reset pending' ? 'warning' : 'success'),
                    TextEntry::make('user.wallet_security_reset_requested_at')
                        ->label('Reset requested')
                        ->dateTime()
                        ->placeholder('No reset requested'),
                    TextEntry::make('user.wallet_security_reset_acknowledged_at')
                        ->label('Acknowledged by app')
                        ->dateTime()
                        ->placeholder('Not acknowledged'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('User')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('balance')->money(fn (GoshenWallet $record): string => $record->currency ?: 'GBP')->sortable(),
                Tables\Columns\TextColumn::make('goal_amount')->label('Goal')->money(fn (GoshenWallet $record): string => $record->currency ?: 'GBP')->sortable()->placeholder('Not set'),
                Tables\Columns\TextColumn::make('goal_label')->label('Goal label')->searchable()->toggleable(),
                Tables\Columns\IconColumn::make('stripe_payment_method_id')->label('Saved card')->boolean()->getStateUsing(fn (GoshenWallet $record): bool => filled($record->stripe_payment_method_id)),
                Tables\Columns\IconColumn::make('user.wallet_security_reset_required')
                    ->label('Security reset')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Started')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make()->label('View wallet'),
                static::walletAdminTopUpAction(),
                static::walletSecurityResetAction(),
            ]);
    }

    public static function walletAdminTopUpAction(): Actions\Action
    {
        return Actions\Action::make('adminWalletTopUp')
            ->label('Top up wallet')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (GoshenWallet $record): bool => static::canAdminTopUpWallet($record))
            ->form(fn (GoshenWallet $record): array => static::walletAdminTopUpForm($record))
            ->modalHeading('Top up member wallet')
            ->modalDescription('This records an admin-approved wallet credit with audit details. Use only after the church has received or approved the matching funds.')
            ->modalSubmitActionLabel('Top up wallet')
            ->action(function (GoshenWallet $record, array $data, GoshenWalletService $wallets): void {
                static::topUpWallet($record, $data, $wallets);
            });
    }

    public static function walletAdminTopUpForm(?GoshenWallet $record = null): array
    {
        $currency = strtoupper((string) ($record?->currency ?: 'GBP'));

        return [
            Forms\Components\TextInput::make('amount')
                ->label('Amount')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->prefix($currency)
                ->required(),
            Forms\Components\TextInput::make('currency')
                ->label('Currency')
                ->default($currency)
                ->maxLength(3)
                ->required()
                ->helperText('Must match the wallet currency.'),
            Forms\Components\Select::make('purpose_type')
                ->label('Purpose')
                ->options([
                    'cash_received' => 'Cash received',
                    'bank_transfer_received' => 'Bank transfer received',
                    'voucher_replacement' => 'Voucher replacement',
                    'balance_correction' => 'Balance correction',
                    'admin_wallet_top_up' => 'Admin wallet top-up',
                    'other' => 'Other',
                ])
                ->default('cash_received')
                ->native(false)
                ->required(),
            Forms\Components\TextInput::make('external_reference')
                ->label('External reference')
                ->maxLength(120)
                ->helperText('Optional receipt, cash log, bank transfer, or support reference.'),
            Forms\Components\Textarea::make('note')
                ->label('Audit note')
                ->helperText('Record why this wallet is being topped up and how the funds were confirmed.')
                ->required()
                ->minLength(12)
                ->maxLength(1000)
                ->rows(4),
            Forms\Components\TextInput::make('confirmation')
                ->label('Type TOP UP WALLET')
                ->helperText('This creates a paid wallet credit immediately.')
                ->required(),
        ];
    }

    public static function topUpWallet(
        GoshenWallet $record,
        array $data,
        GoshenWalletService $wallets,
    ): void {
        $confirmation = trim((string) ($data['confirmation'] ?? ''));
        if ($confirmation !== 'TOP UP WALLET') {
            Notification::make()
                ->title('Confirmation phrase did not match')
                ->body('Type TOP UP WALLET to confirm this wallet credit.')
                ->danger()
                ->send();

            return;
        }

        if (! static::canAdminTopUpWallet($record)) {
            Notification::make()
                ->title('Wallet top-up not allowed')
                ->body('The feature is disabled, the wallet is not linked to a member, or you do not have permission.')
                ->danger()
                ->send();

            return;
        }

        $admin = Auth::user();
        if (! $admin instanceof User) {
            Notification::make()
                ->title('Admin account could not be verified')
                ->danger()
                ->send();

            return;
        }

        try {
            $entry = $wallets->createAdminTopUp($record, $admin, $data);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Wallet top-up was not created')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $record->refresh();

        Notification::make()
            ->title('Wallet topped up')
            ->body(sprintf(
                '%s %s was credited. New balance: %s %s.',
                $entry->currency,
                number_format((float) $entry->amount, 2),
                $record->currency,
                number_format((float) $record->balance, 2),
            ))
            ->success()
            ->send();
    }

    public static function canAdminTopUpWallet(?GoshenWallet $record = null): bool
    {
        $admin = Auth::user();

        if (! $admin || (! $admin->hasRole('super_admin') && ! $admin->can(AdminPermissions::resourcePermission(static::class)))) {
            return false;
        }

        $walletEnabled = filter_var(AppSetting::value('goshen_wallet_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $adminTopUpEnabled = filter_var(AppSetting::value('goshen_wallet_admin_topup_enabled', '1'), FILTER_VALIDATE_BOOLEAN);

        if (! $walletEnabled || ! $adminTopUpEnabled) {
            return false;
        }

        if (! $record) {
            return true;
        }

        $record->loadMissing('user');

        return $record->user !== null;
    }

    public static function walletSecurityResetAction(): Actions\Action
    {
        return Actions\Action::make('resetWalletSecurity')
            ->label('Reset wallet security')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(fn (GoshenWallet $record): bool => static::canResetWalletSecurity($record))
            ->form(static::walletSecurityResetForm())
            ->requiresConfirmation()
            ->modalHeading('Reset wallet security')
            ->modalDescription('Use this only after verifying the member through support. The old wallet PIN will not be viewed or recovered. The member must sign in again and create a new wallet PIN.')
            ->modalSubmitActionLabel('Reset wallet security')
            ->action(function (GoshenWallet $record, array $data, WalletSecurityResetService $resets): void {
                static::requestWalletSecurityReset($record, $data, $resets);
            });
    }

    public static function walletSecurityResetForm(): array
    {
        return [
            Forms\Components\Select::make('verification_method')
                ->label('Verification method')
                ->options([
                    'registered_email_or_phone' => 'Registered email or phone confirmed',
                    'in_person_id' => 'In-person ID check',
                    'church_record' => 'Church membership record confirmed',
                    'recent_wallet_activity' => 'Recent wallet activity confirmed',
                    'other' => 'Other verified support process',
                ])
                ->native(false)
                ->required(),
            Forms\Components\Textarea::make('verification_notes')
                ->label('Verification notes')
                ->helperText('Record who contacted support, how identity was verified, and why the reset is needed.')
                ->required()
                ->minLength(12)
                ->maxLength(1000)
                ->rows(4),
            Forms\Components\TextInput::make('confirmation')
                ->label('Type RESET WALLET SECURITY')
                ->helperText('This invalidates the current mobile session and blocks wallet actions until the app acknowledges reset setup.')
                ->required(),
        ];
    }

    public static function requestWalletSecurityReset(
        GoshenWallet $record,
        array $data,
        WalletSecurityResetService $resets,
    ): void {
        $confirmation = trim((string) ($data['confirmation'] ?? ''));
        if ($confirmation !== 'RESET WALLET SECURITY') {
            Notification::make()
                ->title('Confirmation phrase did not match')
                ->body('Type RESET WALLET SECURITY to confirm this support action.')
                ->danger()
                ->send();

            return;
        }

        if (! static::canResetWalletSecurity($record)) {
            Notification::make()
                ->title('Wallet security reset not allowed')
                ->body('You do not have permission for this action, or a reset is already pending.')
                ->danger()
                ->send();

            return;
        }

        $record->loadMissing('user');
        $mobileUser = $record->user;
        if (! $mobileUser) {
            Notification::make()
                ->title('Wallet has no member')
                ->body('This wallet cannot be reset because it is not linked to a mobile user.')
                ->danger()
                ->send();

            return;
        }

        $admin = Auth::user();

        try {
            $resets->requestReset(
                $mobileUser,
                $admin instanceof User ? $admin : null,
                (string) $data['verification_method'],
                (string) $data['verification_notes'],
                request()->ip(),
                request()->userAgent(),
            );
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Wallet security reset was not created')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $record->refresh();

        Notification::make()
            ->title('Wallet security reset approved')
            ->body('The mobile session was invalidated, the member was notified, and wallet actions are blocked until the app acknowledges the reset.')
            ->success()
            ->send();

    }

    public static function canResetWalletSecurity(?GoshenWallet $record = null): bool
    {
        $admin = Auth::user();

        if (! $admin || (! $admin->hasRole('super_admin') && ! $admin->can(AdminPermissions::WALLET_SECURITY_RESETS))) {
            return false;
        }

        if (! $record) {
            return true;
        }

        $record->loadMissing('user');

        return $record->user !== null
            && ! (bool) $record->user->wallet_security_reset_required;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenWallets::route('/'),
            'view' => Pages\ViewGoshenWallet::route('/{record}'),
        ];
    }
}
