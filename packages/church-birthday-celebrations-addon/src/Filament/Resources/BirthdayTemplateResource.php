<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayTemplate;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
            Section::make('Create a reusable birthday card style')
                ->description('This controls the generated birthday card colours and fallback blessing. Each member\'s display name, photo choice, and preferred verse are applied separately.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Template name')
                        ->placeholder('For example, Gold celebration')
                        ->required()
                        ->maxLength(255)
                        ->helperText('An internal label for church administrators. Members do not see this name.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Available to use')
                        ->default(true)
                        ->helperText('Turn this off to keep the template without using it for new celebrations.'),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Use as the default template')
                        ->live()
                        ->helperText('New celebrations use this template first. Existing celebrations keep their linked template.'),
                ]),
            Section::make('Priority and change history')
                ->description('Only one active default template is needed. Priority is used only when no default template is set.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Fallback priority')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Lower values are chosen first when there is no default template.')
                        ->visible(fn (Get $get): bool => ! (bool) $get('is_default')),
                    Forms\Components\TextInput::make('version')
                        ->label('Design version')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->helperText('Start at 1. Increase this only after a material redesign so the change is easy to trace.'),
                ]),
            Section::make('Card design')
                ->description('Choose the colours used on the square and portrait birthday cards. The preview updates as you choose.')
                ->columns(2)
                ->schema([
                    Forms\Components\ColorPicker::make('background_color')
                        ->label('Brand colour')
                        ->required()
                        ->default('#4A2E62')
                        ->live(),
                    Forms\Components\ColorPicker::make('accent_color')
                        ->label('Accent colour')
                        ->required()
                        ->default('#D49A2A')
                        ->live(),
                    Forms\Components\Textarea::make('verse')
                        ->label('Fallback birthday verse')
                        ->placeholder('For example, May the Lord bless you and keep you.')
                        ->maxLength(500)
                        ->helperText('Used only when a member has not selected a preferred birthday verse.')
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('portrait_preview')
                        ->label('Live card preview')
                        ->helperText('Preview only. The final card uses the celebrant\'s own name and profile-photo preference.')
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

        $square = static::previewCard($background, $accent, '168px', '168px');
        $portrait = static::previewCard($background, $accent, '148px', '186px');

        return new HtmlString('<div style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:20px;">'
            .'<div><div style="margin-bottom:8px; color:#a1a1aa; font-size:12px; font-weight:600;">Square card</div>'.$square.'</div>'
            .'<div><div style="margin-bottom:8px; color:#a1a1aa; font-size:12px; font-weight:600;">Portrait card</div>'.$portrait.'</div>'
            .'</div>');
    }

    private static function previewCard(string $background, string $accent, string $width, string $height): string
    {
        return '<div style="box-sizing:border-box; width:'.$width.'; height:'.$height.'; padding:11px; border:3px solid '.e($accent).'; border-radius:8px; background:'.e($background).'; box-shadow:0 3px 10px rgb(15 23 42 / 18%);">'
            .'<div style="box-sizing:border-box; display:flex; height:100%; flex-direction:column; align-items:center; justify-content:center; padding:12px; border-radius:4px; background:#ffffff; color:#241f2a; text-align:center;">'
            .'<div style="margin-bottom:8px; color:#5b5262; font-size:8px; font-weight:700; letter-spacing:0.08em;">MFM TRIUMPHANT CHURCH</div>'
            .'<div style="margin-bottom:7px; color:'.e($background).'; font-size:17px; font-weight:800; line-height:1.1;">Happy Birthday</div>'
            .'<div style="width:42px; height:3px; margin-bottom:8px; background:'.e($accent).';"></div>'
            .'<div style="font-size:10px; font-weight:600;">Celebrating with your church family</div>'
            .'</div></div>';
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
