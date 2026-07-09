<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FcmTokenResource\Pages;
use App\Models\FcmToken;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FcmTokenResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = FcmToken::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\Textarea::make('token')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('app_version')
                    ->required(),
                Forms\Components\TextInput::make('channel')
                    ->required(),
                Forms\Components\DateTimePicker::make('last_seen_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('app_version')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('channel')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable()
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
            'index' => Pages\ListFcmTokens::route('/'),
            'create' => Pages\CreateFcmToken::route('/create'),
            'edit' => Pages\EditFcmToken::route('/{record}/edit'),
        ];
    }
}
