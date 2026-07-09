<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChurchGroupResource\Pages;
use App\Models\ChurchGroup;
use App\Models\MobileUser;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ChurchGroupResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = ChurchGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Church Groups';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0),
            Forms\Components\Select::make('leader_id')
                ->label('Group leader')
                ->options(fn () => MobileUser::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('assistant_id')
                ->label('Assistant group leader')
                ->options(fn () => MobileUser::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload(),
            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->required(),
            Forms\Components\Textarea::make('functions')
                ->label('Group functions')
                ->rows(5)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('leader.name')
                    ->label('Leader')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assistant.name')
                    ->label('Assistant')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChurchGroups::route('/'),
            'create' => Pages\CreateChurchGroup::route('/create'),
            'edit' => Pages\EditChurchGroup::route('/{record}/edit'),
        ];
    }
}
