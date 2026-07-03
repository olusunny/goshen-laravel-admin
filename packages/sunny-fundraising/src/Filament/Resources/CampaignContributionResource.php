<?php

namespace Sunny\Fundraising\Filament\Resources;

use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Sunny\Fundraising\Filament\Resources\CampaignContributionResource\Pages;
use Sunny\Fundraising\Filament\Resources\Concerns\AuthorizesFundraisingAdmin;
use Sunny\Fundraising\Models\CampaignContribution;

class CampaignContributionResource extends Resource
{
    use AuthorizesFundraisingAdmin;

    protected static ?string $model = CampaignContribution::class;

    protected static ?string $slug = 'fundraising/contributions';

    protected static ?string $modelLabel = 'fundraising contribution';

    protected static ?string $pluralModelLabel = 'fundraising contributions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Fundraising';

    protected static ?int $navigationSort = 30;

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Contribution')
                ->columns(3)
                ->schema([
                    TextEntry::make('campaign.title')->label('Campaign'),
                    TextEntry::make('display_name')->placeholder('Anonymous supporter'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('payment_provider')->label('Payment method')->badge(),
                    TextEntry::make('provider_reference')->label('Provider reference')->copyable()->placeholder('No provider reference'),
                    TextEntry::make('amount')->money(fn (CampaignContribution $record): string => $record->currency ?: 'GBP'),
                    TextEntry::make('currency')->badge(),
                    TextEntry::make('wallet_transaction_id')->copyable()->placeholder('No wallet transaction'),
                    TextEntry::make('message')->columnSpanFull()->placeholder('No message'),
                    TextEntry::make('succeeded_at')->dateTime()->placeholder('Not settled'),
                    TextEntry::make('created_at')->dateTime(),
                ]),
            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->state(fn (CampaignContribution $record): string => json_encode($record->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                        ->copyable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign.title')->label('Campaign')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('display_name')->label('Supporter')->searchable()->placeholder('Anonymous'),
                Tables\Columns\IconColumn::make('is_anonymous')->label('Anonymous')->boolean(),
                Tables\Columns\TextColumn::make('amount')->money(fn (CampaignContribution $record): string => $record->currency ?: 'GBP')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('payment_provider')->label('Method')->badge()->sortable(),
                Tables\Columns\TextColumn::make('provider_reference')->label('Provider reference')->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('wallet_transaction_id')->label('Wallet transaction')->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('succeeded_at')->dateTime()->sortable()->placeholder('Not settled'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaignContributions::route('/'),
            'view' => Pages\ViewCampaignContribution::route('/{record}'),
        ];
    }
}
