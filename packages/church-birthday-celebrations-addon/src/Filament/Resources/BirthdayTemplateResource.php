<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayTemplate;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class BirthdayTemplateResource extends Resource
{
    use AuthorizesBirthdayCelebrationsAdmin;

    protected static ?string $model = BirthdayTemplate::class;

    protected static ?string $slug = 'church-birthday-celebrations/templates';

    protected static ?string $modelLabel = 'birthday template';

    protected static ?string $pluralModelLabel = 'birthday templates';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cake';

    protected static string|\UnitEnum|null $navigationGroup = 'Church Birthday Celebrations';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Template details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default template')
                        ->helperText('The one default wins before sort order. Existing celebrations keep their linked template.'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Lower values are preferred when no default is set.'),
                    Forms\Components\TextInput::make('version')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->helperText('Use a new version when changing a design materially.'),
                    Forms\Components\ColorPicker::make('background_color')
                        ->required()
                        ->default('#4A2E62'),
                    Forms\Components\ColorPicker::make('accent_color')
                        ->required()
                        ->default('#D49A2A'),
                    Forms\Components\Textarea::make('verse')
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('portrait_preview')
                        ->label('Square and portrait preview')
                        ->content(fn ($get): HtmlString => static::preview($get('background_color'), $get('accent_color')))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_default')->label('Default')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
                Tables\Columns\TextColumn::make('version')->badge()->sortable(),
                Tables\Columns\TextColumn::make('background_color')->label('Background')->sortable(),
                Tables\Columns\TextColumn::make('accent_color')->label('Accent')->sortable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn (BirthdayTemplate $record): bool => ! $record->celebrations()->exists()),
            ]);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canManageBirthdayCelebrations()
            && $record instanceof BirthdayTemplate
            && ! $record->celebrations()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function preview(?string $background, ?string $accent): HtmlString
    {
        $background = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $background) ? $background : '#4A2E62';
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $accent) ? $accent : '#D49A2A';

        return new HtmlString('<div class="flex flex-wrap gap-4"><div class="flex h-28 w-28 items-center justify-center rounded-lg p-3 text-center text-sm font-semibold text-white" style="background: '.e($background).'; border: 4px solid '.e($accent).';">Happy Birthday</div><div class="flex h-36 w-28 items-center justify-center rounded-lg p-3 text-center text-sm font-semibold text-white" style="background: '.e($background).'; border: 4px solid '.e($accent).';">Happy Birthday</div></div>');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBirthdayTemplates::route('/'),
            'create' => Pages\CreateBirthdayTemplate::route('/create'),
            'edit' => Pages\EditBirthdayTemplate::route('/{record}/edit'),
        ];
    }
}
