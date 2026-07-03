<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccommodationServiceResource\Pages;
use App\Models\AccommodationService;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AccommodationServiceResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AccommodationService::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';
    protected static string|\UnitEnum|null $navigationGroup = 'Legacy Accommodation Archive';
    protected static ?string $navigationLabel = 'Services';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('icon'),
            Forms\Components\Toggle::make('is_active')->default(true)->required(),
            Forms\Components\Textarea::make('description')->rows(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('description')->limit(60),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAccommodationServices::route('/')];
    }
}
