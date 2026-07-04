<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChurchEventResource\Pages;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Models\ChurchEvent;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ChurchEventResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = ChurchEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Event basics')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('venue')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('theme')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bible_verse')
                            ->label('Bible verse')
                            ->placeholder('Psalm 68:1')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('thumbnail')
                            ->label('Landscape banner / thumbnail')
                            ->disk('public')
                            ->directory('events')
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->previewable()
                            ->downloadable()
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('portrait_image')
                            ->label('Portrait full-screen image')
                            ->disk('public')
                            ->directory('events/portrait')
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->previewable()
                            ->downloadable()
                            ->helperText('Optional 9:16 portrait image used when users tap the event banner for full-screen view.')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('details')
                            ->columnSpanFull(),
                    ]),
                Section::make('Multi-day event schedule')
                    ->description('Use this for events with multiple days and sessions. Example: Friday and Saturday morning, afternoon, and vigil sessions.')
                    ->schema([
                        Forms\Components\Repeater::make('event_schedule')
                            ->label('Event days and sessions')
                            ->schema([
                                Forms\Components\TextInput::make('day_label')
                                    ->label('Day label')
                                    ->placeholder('Friday 12th & Saturday 13th')
                                    ->required()
                                    ->maxLength(180),
                                Forms\Components\TextInput::make('date_label')
                                    ->label('Date label')
                                    ->placeholder('12 & 13 June, 2026')
                                    ->maxLength(180),
                                Forms\Components\Repeater::make('sessions')
                                    ->label('Sessions')
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->label('Session title')
                                            ->placeholder('Morning session, Vigil')
                                            ->maxLength(160),
                                        Forms\Components\TextInput::make('time')
                                            ->label('Time')
                                            ->placeholder('9:00am - 12noon (WAT)')
                                            ->required()
                                            ->maxLength(160),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add session')
                                    ->reorderable(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add event day')
                            ->reorderable(),
                    ]),
                Section::make('Programme people')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('host')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('other_ministers')
                            ->label('Other ministers')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('invited_gospel_musicians')
                            ->label('Invited Gospel Musicians')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Musician name')
                                    ->required()
                                    ->maxLength(160),
                                Forms\Components\FileUpload::make('image')
                                    ->label('Portrait image')
                                    ->disk('public')
                                    ->directory('events/gospel-musicians')
                                    ->image()
                                    ->imageEditor()
                                    ->maxSize(5120)
                                    ->previewable()
                                    ->downloadable()
                                    ->helperText('Upload a portrait photo. It will appear in a swipeable musician section on the event details page.'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add musician')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Live streaming and registration')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Repeater::make('live_streaming_platforms')
                            ->label('Live Streaming Platforms')
                            ->schema([
                                Forms\Components\Select::make('platform')
                                    ->label('Platform')
                                    ->options(self::liveStreamingPlatformOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        $set('url', self::liveStreamingPlatformPresets()[$state] ?? '');
                                    })
                                    ->required()
                                    ->helperText('Select a preset platform to auto-fill the streaming URL.'),
                                Forms\Components\TextInput::make('url')
                                    ->label('Streaming URL')
                                    ->url()
                                    ->helperText('You can edit this URL if this event uses a different stream link.')
                                    ->maxLength(2048),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add platform')
                            ->reorderable(false)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('registration_url')
                            ->label('Registration link')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('registration_availability')
                            ->label('Registration availability')
                            ->options([
                                'nigeria' => 'Available in Nigeria',
                                'outside_nigeria' => 'Outside Nigeria Only',
                                'everywhere' => 'Available everywhere',
                            ])
                            ->default('everywhere')
                            ->required(),
                        Forms\Components\DateTimePicker::make('starts_at'),
                        Forms\Components\DateTimePicker::make('ends_at'),
                        Forms\Components\Toggle::make('is_pilgrimage')
                            ->label('Pilgrimage event')
                            ->live()
                            ->helperText('Turn this on to show pilgrimage-specific fields in the app.'),
                        Forms\Components\Toggle::make('is_published')
                            ->required(),
                        Forms\Components\Hidden::make('legacy_id'),
                    ]),
                Section::make('Programme recurrence')
                    ->description('Schedule weekly or monthly church programmes from one event record.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('recurrence_type')
                            ->label('Repeat schedule')
                            ->options(ChurchEvent::recurrenceOptions())
                            ->default(ChurchEvent::RECURRENCE_NONE)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if ($state === ChurchEvent::RECURRENCE_NONE) {
                                    $set('recurrence_weekday', null);
                                    $set('recurrence_week_of_month', null);
                                    $set('recurrence_until', null);

                                    return;
                                }

                                if ($get('recurrence_weekday') === null) {
                                    $set('recurrence_weekday', 0);
                                }

                                if ($state === ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY && $get('recurrence_week_of_month') === null) {
                                    $set('recurrence_week_of_month', 1);
                                }
                            }),
                        Forms\Components\TextInput::make('recurrence_interval')
                            ->label('Repeat every')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(1)
                            ->required()
                            ->suffix(fn (Get $get): string => $get('recurrence_type') === ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY ? 'month(s)' : 'week(s)')
                            ->visible(fn (Get $get): bool => $get('recurrence_type') !== ChurchEvent::RECURRENCE_NONE),
                        Forms\Components\Select::make('recurrence_weekday')
                            ->label('Day of week')
                            ->options(ChurchEvent::weekdayOptions())
                            ->native(false)
                            ->required(fn (Get $get): bool => $get('recurrence_type') !== ChurchEvent::RECURRENCE_NONE)
                            ->visible(fn (Get $get): bool => $get('recurrence_type') !== ChurchEvent::RECURRENCE_NONE),
                        Forms\Components\Select::make('recurrence_week_of_month')
                            ->label('Week of month')
                            ->options(ChurchEvent::weekOfMonthOptions())
                            ->native(false)
                            ->required(fn (Get $get): bool => $get('recurrence_type') === ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY)
                            ->visible(fn (Get $get): bool => $get('recurrence_type') === ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY),
                        Forms\Components\DatePicker::make('recurrence_until')
                            ->label('Repeat until')
                            ->helperText('Optional. Leave empty to keep generating future occurrences for the app calendar.')
                            ->visible(fn (Get $get): bool => $get('recurrence_type') !== ChurchEvent::RECURRENCE_NONE),
                    ]),
                Section::make('Pilgrimage details')
                    ->description('Shown only when this event is marked as a Pilgrimage event.')
                    ->visible(fn (Get $get): bool => (bool) $get('is_pilgrimage'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pilgrimage_details.organizer')
                            ->label('Organizer')
                            ->placeholder('C.A.C MFM Triumphant Church')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pilgrimage_details.packaged_by')
                            ->label('Packaged by')
                            ->placeholder('UfitFly')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('pilgrimage_details.theme')
                            ->label('Pilgrimage theme')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('pilgrimage_details.country_venue')
                            ->label('Country / Venue')
                            ->placeholder('Israel')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pilgrimage_details.date_text')
                            ->label('Pilgrimage date')
                            ->placeholder('22nd - 27th Sept 2026')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('pilgrimage_details.ministering')
                            ->label('Ministering')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('pilgrimage_details.participation_fees')
                            ->label('Participation Fee Breakdown')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Category')
                                    ->placeholder('Diaspora UK')
                                    ->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->placeholder('£1,100')
                                    ->required(),
                                Forms\Components\TextInput::make('note')
                                    ->label('Note')
                                    ->placeholder('Flight ticket excluded'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add fee')
                            ->reorderable(false)
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('pilgrimage_details.payment_details')
                            ->label('Payment details')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Payment section')
                                    ->placeholder('Payment details for Nigeria')
                                    ->required()
                                    ->maxLength(180),
                                Forms\Components\Textarea::make('details')
                                    ->label('Account / bank details')
                                    ->rows(6)
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Add payment section')
                            ->reorderable(false)
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('pilgrimage_details.registration_contacts')
                            ->label('Registration contacts')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Contact name')
                                    ->required(),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone number')
                                    ->tel()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add registration contact')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
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
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('venue')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('theme')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_pilgrimage')
                    ->label('Pilgrimage')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recurrence_type')
                    ->label('Programme')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        ChurchEvent::RECURRENCE_WEEKLY => 'Weekly',
                        ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY => 'Monthly',
                        default => 'One-time',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('registration_availability')
                    ->label('Registration')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'nigeria' => 'Nigeria',
                        'outside_nigeria' => 'Outside Nigeria',
                        default => 'Everywhere',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
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

    private static function liveStreamingPlatformPresets(): array
    {
        return [
            'Facebook' => 'https://www.facebook.com/prophettaiwoojolive',
            'YouTube' => 'https://www.youtube.com/@PROPHETTAIWOOJO/videos',
            'Telegram' => 'https://t.me/prophettaiwoojolive',
            'Mixlr' => 'https://mixlr.com/prophettaiwoojo/',
        ];
    }

    private static function liveStreamingPlatformOptions(): array
    {
        return collect(self::liveStreamingPlatformPresets())
            ->keys()
            ->mapWithKeys(fn (string $platform): array => [$platform => $platform])
            ->all();
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
            'index' => Pages\ListChurchEvents::route('/'),
            'create' => Pages\CreateChurchEvent::route('/create'),
            'edit' => Pages\EditChurchEvent::route('/{record}/edit'),
        ];
    }
}
