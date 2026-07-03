<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationAccountCategoryResource\Pages;
use App\Models\DonationAccountCategory;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DonationAccountCategoryResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = DonationAccountCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|\UnitEnum|null $navigationGroup = 'Giving';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255),
                Forms\Components\TextInput::make('currency_code')
                    ->required()
                    ->maxLength(3)
                    ->default('NGN'),
                Forms\Components\TextInput::make('country_code')
                    ->maxLength(2)
                    ->helperText('ISO code, for example NG, US, GB, CA, EU.'),
                Forms\Components\TextInput::make('flag_icon')
                    ->label('Flag icon')
                    ->maxLength(16)
                    ->helperText('Use the colored flag for this giving account category.'),
                Forms\Components\ColorPicker::make('color'),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('flag_icon')
                    ->label('')
                    ->size('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('currency_code')
                    ->badge()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\ColorColumn::make('color')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDonationAccountCategories::route('/'),
            'create' => Pages\CreateDonationAccountCategory::route('/create'),
            'edit' => Pages\EditDonationAccountCategory::route('/{record}/edit'),
        ];
    }
}
