<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenRetreatMaterialResource\Pages;
use App\Models\GoshenRetreatMaterial;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GoshenRetreatMaterialResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenRetreatMaterial::class;

    protected static ?string $modelLabel = 'Goshen retreat material';

    protected static ?string $pluralModelLabel = 'Goshen retreat materials';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Material')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->relationship(
                            name: 'event',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                                $query
                                    ->where('settings->module', 'goshen_retreat')
                                    ->orWhere('settings->module', 'goshen-retreat')
                                    ->orWhere('settings->app_module', 'goshen_retreat')
                                    ->orWhere('slug', 'like', 'goshen-%')
                                    ->orWhere('name', 'like', '%Goshen Retreat%');
                            }),
                        )
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Available to ticket holders')
                        ->default(true),
                    Forms\Components\FileUpload::make('file_path')
                        ->label('PDF or image')
                        ->disk('local')
                        ->directory('goshen/retreat/materials')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(15360)
                        ->storeFileNamesIn('filename')
                        ->previewable(false)
                        ->downloadable(false)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('event.name')->label('Retreat edition')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('filename')->label('File')->limit(40),
                Tables\Columns\TextColumn::make('mime_type')->label('Type'),
                Tables\Columns\TextColumn::make('file_size')->label('Size')->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB'),
                Tables\Columns\IconColumn::make('is_published')->label('Available')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\CreateAction::make(),
                Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenRetreatMaterials::route('/'),
            'create' => Pages\CreateGoshenRetreatMaterial::route('/create'),
            'edit' => Pages\EditGoshenRetreatMaterial::route('/{record}/edit'),
        ];
    }
}
