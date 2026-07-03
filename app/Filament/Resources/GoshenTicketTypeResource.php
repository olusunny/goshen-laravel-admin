<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenTicketTypeResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Personal\EventInstallments\Models\EventTicketType;

class GoshenTicketTypeResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = EventTicketType::class;

    protected static ?string $modelLabel = 'Goshen ticket type';

    protected static ?string $pluralModelLabel = 'Goshen ticket types';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Ticket type')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')->relationship('event', 'name')->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('sku')->maxLength(255),
                    Forms\Components\TextInput::make('currency')->default('NGN')->required()->maxLength(3),
                    Forms\Components\TextInput::make('price')->numeric()->default(0)->required(),
                    Forms\Components\TextInput::make('capacity')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('min_per_booking')->numeric()->minValue(1)->default(1)->required(),
                    Forms\Components\TextInput::make('max_per_booking')->numeric()->minValue(1),
                    Forms\Components\Toggle::make('is_active')->default(true)->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('price')->money(fn ($record) => $record->currency ?: 'NGN')->sortable(),
                Tables\Columns\TextColumn::make('capacity')->placeholder('Unlimited'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([Actions\EditAction::make()])
            ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenTicketTypes::route('/'),
            'create' => Pages\CreateGoshenTicketType::route('/create'),
            'edit' => Pages\EditGoshenTicketType::route('/{record}/edit'),
        ];
    }
}
