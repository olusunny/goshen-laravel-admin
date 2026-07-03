<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenReferralPointEntryResource\Pages;
use App\Models\GoshenReferralPointEntry;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GoshenReferralPointEntryResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenReferralPointEntry::class;

    protected static ?string $modelLabel = 'referral point';

    protected static ?string $pluralModelLabel = 'referral points';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?int $navigationSort = 44;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Referral award')
                ->columns(3)
                ->schema([
                    TextEntry::make('status')->badge(),
                    TextEntry::make('points')->numeric(),
                    TextEntry::make('converted_points')->numeric(),
                    TextEntry::make('referrer.name')->label('Referrer')->placeholder('No referrer'),
                    TextEntry::make('referrer.email')->label('Referrer email')->copyable()->placeholder('No email'),
                    TextEntry::make('referralCode.code')->label('Referral code')->copyable(),
                    TextEntry::make('referee.name')->label('Referred member')->placeholder('No member'),
                    TextEntry::make('referee.email')->label('Referred email')->copyable()->placeholder('No email'),
                    TextEntry::make('event.name')->label('Retreat edition')->placeholder('No event'),
                    TextEntry::make('booking.public_id')->label('Booking')->copyable()->placeholder('No booking'),
                    TextEntry::make('validated_at')->dateTime()->placeholder('Not validated'),
                    TextEntry::make('converted_at')->dateTime()->placeholder('Not converted'),
                    TextEntry::make('notified_at')->dateTime()->placeholder('Not notified'),
                    TextEntry::make('walletLedgerEntry.provider_reference')->label('Wallet reference')->copyable()->placeholder('No wallet conversion'),
                ]),
            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->formatStateUsing(fn ($state): string => json_encode($state ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->copyable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('referrer.name')->label('Referrer')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('referralCode.code')->label('Code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('referee.name')->label('Referred member')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('event.name')->label('Retreat')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('points')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('converted_points')->label('Converted')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('validated_at')->dateTime()->sortable()->placeholder('Not validated'),
                Tables\Columns\TextColumn::make('converted_at')->dateTime()->sortable()->placeholder('Not converted'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                \Filament\Actions\ViewAction::make()->label('View award'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenReferralPointEntries::route('/'),
            'view' => Pages\ViewGoshenReferralPointEntry::route('/{record}'),
        ];
    }
}
