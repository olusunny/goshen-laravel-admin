<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportationArrangementResource\Pages;
use App\Models\TransportationArrangement;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TransportationArrangementResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = TransportationArrangement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Programs';

    protected static ?string $navigationLabel = 'Transportation Arrangements';

    protected static ?string $modelLabel = 'Transportation Arrangement';

    protected static ?string $pluralModelLabel = 'Transportation Arrangements';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Program and Pickup Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('program_name')
                            ->default('72Hours')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('event_title')
                            ->label('Event title')
                            ->placeholder('72Hours Transportation')
                            ->maxLength(160)
                            ->helperText('Shown in the mobile app for this transportation route.'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                        Forms\Components\TextInput::make('city_town')
                            ->label('City / Town')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('state')
                            ->maxLength(120),
                        Forms\Components\Textarea::make('bus_location')
                            ->label('Bus location / Pickup point')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Bus and Contact Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('bus_type')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('passenger_capacity')
                            ->label('Bus passenger capacity')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000),
                        Forms\Components\TextInput::make('buses_available')
                            ->label('Number of buses available')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->nullable()
                            ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null)
                            ->helperText('Optional. Leave blank when the exact number is not confirmed.'),
                        Forms\Components\TextInput::make('driver_name')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('driver_phone')
                            ->tel()
                            ->maxLength(40),
                        Forms\Components\Repeater::make('contacts')
                            ->label('Pickup contacts')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Contact name')
                                    ->maxLength(120)
                                    ->required(),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Contact phone')
                                    ->tel()
                                    ->maxLength(40)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->addActionLabel('Add another contact')
                            ->reorderable(false)
                            ->helperText('Use this when a pickup station has one or more contact persons.'),
                        Forms\Components\Hidden::make('contact_person_name'),
                        Forms\Components\Hidden::make('contact_person_phone'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('program_name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('event_title')
                    ->label('Event title')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city_town')
                    ->label('City / Town')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bus_location')
                    ->label('Pickup point')
                    ->limit(48)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bus_type')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('passenger_capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('buses_available')
                    ->label('Buses')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('driver_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('driver_phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('contacts')
                    ->label('Pickup contacts')
                    ->state(fn (TransportationArrangement $record): string => collect($record->contactList())
                        ->map(fn (array $contact): string => trim(($contact['name'] ?? '').' '.($contact['phone'] ?? '')))
                        ->filter()
                        ->join(', '))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active status'),
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
            'index' => Pages\ListTransportationArrangements::route('/'),
            'create' => Pages\CreateTransportationArrangement::route('/create'),
            'edit' => Pages\EditTransportationArrangement::route('/{record}/edit'),
        ];
    }

    public static function syncLegacyContactFields(array $data): array
    {
        $contacts = collect($data['contacts'] ?? [])
            ->map(fn (array $contact): array => [
                'name' => trim((string) ($contact['name'] ?? '')),
                'phone' => trim((string) ($contact['phone'] ?? '')),
            ])
            ->filter(fn (array $contact): bool => $contact['name'] !== '' || $contact['phone'] !== '')
            ->values()
            ->all();

        $data['contacts'] = $contacts;
        $data['contact_person_name'] = $contacts[0]['name'] ?? null;
        $data['contact_person_phone'] = $contacts[0]['phone'] ?? null;

        return $data;
    }
}
