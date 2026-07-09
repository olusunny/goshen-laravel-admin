<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrayerPointResource\Pages;
use App\Models\PrayerPoint;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PrayerPointResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = PrayerPoint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\DatePicker::make('date'),
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\TextInput::make('author'),
                Forms\Components\RichEditor::make('content')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('thumbnail')
                    ->disk('public')
                    ->directory('prayers')
                    ->image()
                    ->imageEditor()
                    ->maxSize(5120)
                    ->previewable()
                    ->downloadable(),
                Forms\Components\Toggle::make('is_published')
                    ->label('Published in Prayer Points')
                    ->helperText('Controls whether this prayer point is visible on the standalone Prayer Points page.')
                    ->required(),
                Forms\Components\Toggle::make('show_on_prayer_wall')
                    ->label('Show above Interactive Prayer Wall')
                    ->helperText('Turn this off when the prayer point should remain in Prayer Points but not appear above user prayer wall activities.')
                    ->default(true)
                    ->required(),
                Forms\Components\Hidden::make('legacy_id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->disk('public')
                    ->square()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('show_on_prayer_wall')
                    ->label('On prayer wall')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrayerPoints::route('/'),
            'create' => Pages\CreatePrayerPoint::route('/create'),
            'edit' => Pages\EditPrayerPoint::route('/{record}/edit'),
        ];
    }
}
