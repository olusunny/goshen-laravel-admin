<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VerseOfDayResource\Pages;
use App\Models\VerseOfDay;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class VerseOfDayResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = VerseOfDay::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Verse of the Day';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Scheduled verse')
                ->columns(2)
                ->schema([
                    Forms\Components\DatePicker::make('date')->required()->unique(ignoreRecord: true),
                    Forms\Components\Toggle::make('is_published')->default(true)->required(),
                    Forms\Components\TextInput::make('reference')->placeholder('Psalm 23:1')->required()->maxLength(120),
                    Forms\Components\TextInput::make('version')->default('KJV')->required()->maxLength(40),
                    Forms\Components\Textarea::make('text')->required()->rows(4)->columnSpanFull(),
                    Forms\Components\Textarea::make('reflection')->rows(4)->columnSpanFull(),
                    Forms\Components\Textarea::make('prayer')->rows(3)->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('published_at')->default(now()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('reference')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('version')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('is_published')->boolean()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
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
            'index' => Pages\ListVerseOfDays::route('/'),
            'create' => Pages\CreateVerseOfDay::route('/create'),
            'edit' => Pages\EditVerseOfDay::route('/{record}/edit'),
        ];
    }
}
