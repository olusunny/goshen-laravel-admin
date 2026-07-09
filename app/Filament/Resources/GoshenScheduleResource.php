<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenScheduleResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Personal\EventInstallments\Models\EventSchedule;

class GoshenScheduleResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = EventSchedule::class;

    protected static ?string $modelLabel = 'Goshen schedule session';

    protected static ?string $pluralModelLabel = 'Goshen schedule sessions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Session')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->relationship('event', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('day_number')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    Forms\Components\DateTimePicker::make('starts_at')->required(),
                    Forms\Components\DateTimePicker::make('ends_at'),
                    Forms\Components\TextInput::make('capacity')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('metadata.title')
                        ->label('Session title')
                        ->placeholder('Morning session, vigil, evening session')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('day_number')->sortable(),
                Tables\Columns\TextColumn::make('metadata.title')->label('Session'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('capacity')->placeholder('Unlimited'),
            ])
            ->recordActions([Actions\EditAction::make()])
            ->toolbarActions([
                Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenSchedules::route('/'),
            'create' => Pages\CreateGoshenSchedule::route('/create'),
            'edit' => Pages\EditGoshenSchedule::route('/{record}/edit'),
        ];
    }
}
