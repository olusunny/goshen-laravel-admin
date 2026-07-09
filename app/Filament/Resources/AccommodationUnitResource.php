<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccommodationUnitResource\Pages;
use App\Models\AccommodationCategory;
use App\Models\AccommodationUnit;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AccommodationUnitResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AccommodationUnit::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';
    protected static string|\UnitEnum|null $navigationGroup = 'Legacy Accommodation Archive';
    protected static ?string $navigationLabel = 'Rooms / Units';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('accommodation_category_id')->label('Category')->options(fn () => AccommodationCategory::orderBy('name')->pluck('name', 'id'))->searchable()->preload()->required(),
            Forms\Components\TextInput::make('unit_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('unit_number')->maxLength(80),
            Forms\Components\Select::make('status')->options(['available' => 'Available', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance', 'blocked' => 'Blocked', 'inactive' => 'Inactive'])->default('available')->required(),
            Forms\Components\Toggle::make('is_active')->default(true)->required(),
            Forms\Components\Textarea::make('notes')->rows(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('category.name')->label('Category')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('unit_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('unit_number')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
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
        return [
            'index' => Pages\ListAccommodationUnits::route('/'),
        ];
    }
}
