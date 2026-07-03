<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenQuizWinnerResource\Pages;
use App\Models\GoshenQuizWinner;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GoshenQuizWinnerResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenQuizWinner::class;

    protected static ?string $modelLabel = 'Quiz winner';

    protected static ?string $pluralModelLabel = 'Quiz winners';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Winner')
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\TextInput::make('quiz.title')
                        ->label('Quiz')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('mobileUser.name')
                        ->label('Winner')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('rank')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('score')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('elapsed_seconds')
                        ->label('Elapsed seconds')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('wallet_prize_status')
                        ->label('Wallet prize status')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('wallet_transfer_reference')
                        ->label('Wallet transfer reference')
                        ->disabled()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Winner summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('quiz.title')->label('Quiz')->placeholder('No quiz'),
                    TextEntry::make('mobileUser.name')->label('Winner')->placeholder('Unknown'),
                    TextEntry::make('mobileUser.email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('rank'),
                    TextEntry::make('score')->placeholder('-'),
                    TextEntry::make('elapsed_seconds')->label('Elapsed seconds')->placeholder('-'),
                    TextEntry::make('selected_at')->dateTime()->placeholder('Not selected'),
                    TextEntry::make('prize_label')->placeholder('No visible prize label'),
                    TextEntry::make('wallet_prize_status')->badge(),
                    TextEntry::make('wallet_prize_amount')
                        ->money(fn (GoshenQuizWinner $record): string => $record->wallet_prize_currency ?: 'GBP')
                        ->placeholder('No wallet prize'),
                    TextEntry::make('walletSponsor.name')->label('Wallet sponsor')->placeholder('Not paid'),
                    TextEntry::make('wallet_transfer_reference')->copyable()->placeholder('No transfer yet'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('rank')
            ->columns([
                Tables\Columns\TextColumn::make('quiz.title')
                    ->label('Quiz')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rank')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mobileUser.name')
                    ->label('Winner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->sortable(),
                Tables\Columns\TextColumn::make('elapsed_seconds')
                    ->label('Elapsed')
                    ->formatStateUsing(fn ($state): string => $state === null ? '-' : gmdate('H:i:s', max(0, (int) $state)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('prize_label')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wallet_prize_amount')
                    ->money(fn (GoshenQuizWinner $record): string => $record->wallet_prize_currency ?: 'GBP')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wallet_prize_status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('selected_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('wallet_prize_status')
                    ->options([
                        GoshenQuizWinner::WALLET_PRIZE_NOT_CONFIGURED => 'Not configured',
                        GoshenQuizWinner::WALLET_PRIZE_PENDING => 'Pending',
                        GoshenQuizWinner::WALLET_PRIZE_PAID => 'Paid',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenQuizWinners::route('/'),
            'view' => Pages\ViewGoshenQuizWinner::route('/{record}'),
        ];
    }
}
