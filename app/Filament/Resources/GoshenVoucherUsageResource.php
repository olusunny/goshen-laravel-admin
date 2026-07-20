<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenVoucherUsageResource\Pages;
use App\Models\GoshenVoucherUsage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class GoshenVoucherUsageResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenVoucherUsage::class;

    protected static ?string $modelLabel = 'Goshen voucher usage';

    protected static ?string $pluralModelLabel = 'Goshen voucher usage';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Retreat edition')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('booking.public_id')
                    ->label('Booking')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mobileUser.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mobileUser.email')
                    ->label('Member email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('redeemedByMobileUser.name')
                    ->label('Redeemed by')
                    ->placeholder('Self')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('redeemedBy.name')
                    ->label('Admin')
                    ->placeholder('Not applicable')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('code_suffix')
                    ->label('Code suffix')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn (GoshenVoucherUsage $record): string => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'mobile_registration' => 'Mobile registration',
                        'mobile_existing_booking' => 'Existing booking',
                        'control_hub' => 'Control hub',
                        'admin_panel' => 'Admin panel',
                        'wallet_top_up' => 'Wallet voucher top-up',
                        'control_hub_wallet_voucher_top_up' => 'Control Hub wallet voucher',
                        'admin_wallet_voucher_top_up' => 'Admin wallet voucher',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenVoucherUsages::route('/'),
        ];
    }
}
