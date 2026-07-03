<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccommodationBlockedDateResource\Pages;
use App\Models\AccommodationBlockedDate;
use App\Models\AccommodationCategory;
use App\Models\AccommodationUnit;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AccommodationBlockedDateResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AccommodationBlockedDate::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';
    protected static string|\UnitEnum|null $navigationGroup = 'Legacy Accommodation Archive';
    protected static ?string $navigationLabel = 'Blocked Dates';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('accommodation_category_id')->label('Category')->options(fn () => AccommodationCategory::orderBy('name')->pluck('name', 'id'))->searchable()->preload()->required(),
            Forms\Components\Select::make('accommodation_unit_id')->label('Specific unit')->options(fn () => AccommodationUnit::orderBy('unit_name')->pluck('unit_name', 'id'))->searchable()->preload(),
            Forms\Components\DatePicker::make('start_date')->required(),
            Forms\Components\DatePicker::make('end_date')->required(),
            Forms\Components\TextInput::make('reason')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('category.name')->label('Category')->searchable(),
            Tables\Columns\TextColumn::make('unit.unit_name')->label('Unit'),
            Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
            Tables\Columns\TextColumn::make('end_date')->date()->sortable(),
            Tables\Columns\TextColumn::make('reason')->limit(60),
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
        return ['index' => Pages\ListAccommodationBlockedDates::route('/')];
    }
}
