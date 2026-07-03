<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenRegistrationFieldResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;

class GoshenRegistrationFieldResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = EventAttendeeField::class;

    protected static ?string $modelLabel = 'Goshen registration field';

    protected static ?string $pluralModelLabel = 'Goshen registration fields';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('event', fn (Builder $query) => self::applyGoshenEventScope($query));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Field')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Retreat edition')
                        ->options(fn (): array => self::goshenEventOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('key')
                        ->required()
                        ->maxLength(80)
                        ->regex('/^[a-z][a-z0-9_]*$/')
                        ->rules(
                            fn (Get $get, ?EventAttendeeField $record): array => [
                                Rule::unique('ei_event_attendee_fields', 'key')
                                    ->where('event_id', $get('event_id'))
                                    ->ignore($record?->id),
                            ],
                        )
                        ->helperText('Use lowercase letters, numbers, and underscores only. Existing app keys include designation, gender, age_group, free_church_bus_interest, and volunteer_department.'),
                    Forms\Components\Select::make('type')
                        ->options([
                            'text' => 'Text',
                            'textarea' => 'Long text',
                            'select' => 'Dropdown single select',
                            'image_select' => 'Image option select',
                            'color_select' => 'Colour option select',
                        ])
                        ->default('text')
                        ->native(false)
                        ->required(),
                    Forms\Components\Toggle::make('is_required')
                        ->label('Required')
                        ->default(false),
                    Forms\Components\Toggle::make('is_unique')
                        ->label('Unique per attendee')
                        ->default(false)
                        ->helperText('Reserved for future duplicate checks.'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ]),
            Section::make('Options')
                ->description('Required for dropdown, image option, and colour option fields. Add a blank value labelled Please Select when you want users to actively choose an option.')
                ->schema([
                    Forms\Components\Repeater::make('options')
                        ->label('Choices')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->required()
                                ->maxLength(120),
                            Forms\Components\TextInput::make('value')
                                ->maxLength(120)
                                ->helperText('Leave empty only for a Please Select placeholder. Otherwise use a stable lowercase value.'),
                            Forms\Components\FileUpload::make('image_path')
                                ->label('Option image')
                                ->disk('public')
                                ->directory('goshen/registration/options')
                                ->image()
                                ->imageEditor()
                                ->downloadable()
                                ->previewable(),
                            Forms\Components\ColorPicker::make('color_hex')
                                ->label('Colour'),
                            Forms\Components\TextInput::make('fee_amount')
                                ->label('Fee amount')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->helperText('Optional product/option fee added once per attendee that selects this choice.'),
                            Forms\Components\TextInput::make('fee_label')
                                ->label('Fee label')
                                ->maxLength(120),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Order')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->addActionLabel('Add choice')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Retreat edition')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Retreat edition')
                    ->options(fn (): array => self::goshenEventOptions()),
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
            'index' => Pages\ListGoshenRegistrationFields::route('/'),
            'create' => Pages\CreateGoshenRegistrationField::route('/create'),
            'edit' => Pages\EditGoshenRegistrationField::route('/{record}/edit'),
        ];
    }

    private static function goshenEventOptions(): array
    {
        return Event::query()
            ->where(fn (Builder $query) => self::applyGoshenEventScope($query))
            ->orderByDesc('sales_start_at')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function applyGoshenEventScope(Builder $query): void
    {
        $query
            ->where('settings->module', 'goshen_retreat')
            ->orWhere('settings->module', 'goshen-retreat')
            ->orWhere('settings->app_module', 'goshen_retreat')
            ->orWhere('slug', 'like', 'goshen-retreat%')
            ->orWhere('slug', 'like', 'goshen-%')
            ->orWhere('name', 'like', '%Goshen Retreat%');
    }
}
