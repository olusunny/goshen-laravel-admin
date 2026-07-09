<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenWalletWithdrawalRequestResource\Pages;
use App\Models\GoshenWalletWithdrawalRequest;
use App\Services\GoshenWalletService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class GoshenWalletWithdrawalRequestResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenWalletWithdrawalRequest::class;

    protected static ?string $modelLabel = 'wallet withdrawal request';

    protected static ?string $pluralModelLabel = 'wallet withdrawal requests';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('status')
                ->options(self::statusOptions())
                ->disabled()
                ->dehydrated(false)
                ->required(),
            Forms\Components\Textarea::make('admin_note')
                ->label('Admin note')
                ->rows(3),
            Forms\Components\TextInput::make('payout_reference')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('mobileUser.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mobileUser.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn (GoshenWalletWithdrawalRequest $record): string => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GoshenWalletWithdrawalRequest::STATUS_PENDING => 'warning',
                        GoshenWalletWithdrawalRequest::STATUS_APPROVED => 'info',
                        GoshenWalletWithdrawalRequest::STATUS_PAID => 'success',
                        GoshenWalletWithdrawalRequest::STATUS_REJECTED,
                        GoshenWalletWithdrawalRequest::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('bank_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_code')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payout_reference')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                self::statusAction('approve', 'Approve', GoshenWalletWithdrawalRequest::STATUS_APPROVED),
                self::statusAction('markPaid', 'Mark paid', GoshenWalletWithdrawalRequest::STATUS_PAID),
                self::statusAction('reject', 'Reject', GoshenWalletWithdrawalRequest::STATUS_REJECTED),
                Actions\EditAction::make()->label('Notes'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenWalletWithdrawalRequests::route('/'),
            'edit' => Pages\EditGoshenWalletWithdrawalRequest::route('/{record}/edit'),
        ];
    }

    private static function statusOptions(): array
    {
        return [
            GoshenWalletWithdrawalRequest::STATUS_PENDING => 'Pending',
            GoshenWalletWithdrawalRequest::STATUS_APPROVED => 'Approved',
            GoshenWalletWithdrawalRequest::STATUS_REJECTED => 'Rejected',
            GoshenWalletWithdrawalRequest::STATUS_PAID => 'Paid',
            GoshenWalletWithdrawalRequest::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    private static function statusAction(string $name, string $label, string $status): Actions\Action
    {
        return Actions\Action::make($name)
            ->label($label)
            ->requiresConfirmation()
            ->form([
                Forms\Components\Textarea::make('admin_note')
                    ->rows(3),
                Forms\Components\TextInput::make('payout_reference')
                    ->visible($status === GoshenWalletWithdrawalRequest::STATUS_PAID),
            ])
            ->visible(fn (GoshenWalletWithdrawalRequest $record): bool => ! in_array($record->status, [
                GoshenWalletWithdrawalRequest::STATUS_REJECTED,
                GoshenWalletWithdrawalRequest::STATUS_PAID,
                GoshenWalletWithdrawalRequest::STATUS_CANCELLED,
            ], true))
            ->action(function (GoshenWalletWithdrawalRequest $record, array $data) use ($status): void {
                try {
                    app(GoshenWalletService::class)->updateWithdrawalStatus($record, $status, [
                        'admin_note' => $data['admin_note'] ?? null,
                        'payout_reference' => $data['payout_reference'] ?? null,
                    ]);
                    Notification::make()->title('Withdrawal request updated')->success()->send();
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title('Withdrawal request could not be updated')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
