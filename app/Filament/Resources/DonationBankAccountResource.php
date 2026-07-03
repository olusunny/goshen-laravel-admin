<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationBankAccountResource\Pages;
use App\Models\DonationBankAccount;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DonationBankAccountResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = DonationBankAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|\UnitEnum|null $navigationGroup = 'Giving';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Account category')
                    ->schema([
                        Forms\Components\Select::make('donation_account_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),
                Section::make('Bank details')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sort_code')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('routing_number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('swift_code')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('iban')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('instructions')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('category.flag_icon')
                    ->label('')
                    ->size('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bank_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.currency_code')
                    ->label('Currency')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('donation_account_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
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
            'index' => Pages\ListDonationBankAccounts::route('/'),
            'create' => Pages\CreateDonationBankAccount::route('/create'),
            'edit' => Pages\EditDonationBankAccount::route('/{record}/edit'),
        ];
    }
}
