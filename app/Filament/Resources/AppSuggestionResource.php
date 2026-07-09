<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSuggestionResource\Pages;
use App\Models\AppSuggestion;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AppSuggestionResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AppSuggestion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('sender_name')
                ->disabled(),
            Forms\Components\TextInput::make('sender_email')
                ->disabled(),
            Forms\Components\TextInput::make('subject')
                ->maxLength(160),
            Forms\Components\Select::make('status')
                ->options([
                    'new' => 'New',
                    'reviewing' => 'Reviewing',
                    'closed' => 'Closed',
                ])
                ->default('new')
                ->required(),
            Forms\Components\Textarea::make('message')
                ->rows(8)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('app_version')
                ->disabled(),
            Forms\Components\TextInput::make('device')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sender_name')
                    ->label('Sender')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sender_email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(90)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'reviewing' => 'Reviewing',
                        'closed' => 'Closed',
                    ]),
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
            'index' => Pages\ListAppSuggestions::route('/'),
            'edit' => Pages\EditAppSuggestion::route('/{record}/edit'),
        ];
    }
}
