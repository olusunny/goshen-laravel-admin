<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayVerseResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayVerse;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BirthdayVerseResource extends Resource
{
    use AuthorizesBirthdayCelebrationsAdmin;

    protected static ?string $model = BirthdayVerse::class;
    protected static ?string $slug = 'church-birthday-celebrations/approved-verses';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|\UnitEnum|null $navigationGroup = 'Church Birthday Celebrations';
    protected static ?string $navigationLabel = 'Approved verses';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Approved verse')->columns(2)->schema([
                Forms\Components\TextInput::make('reference')->required()->maxLength(120),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Textarea::make('body')->required()->maxLength(500)->rows(4)->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('body')->limit(90)->searchable(),
            Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
            Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
        ])->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()->requiresConfirmation()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBirthdayVerses::route('/'), 'create' => Pages\CreateBirthdayVerse::route('/create'), 'edit' => Pages\EditBirthdayVerse::route('/{record}/edit')];
    }
}
