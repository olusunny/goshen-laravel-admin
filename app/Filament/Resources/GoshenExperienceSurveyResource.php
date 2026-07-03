<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenExperienceSurveyResource\Pages;
use App\Models\GoshenExperienceQuestion;
use App\Models\GoshenExperienceSurvey;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Models\Event;
use UnitEnum;

class GoshenExperienceSurveyResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenExperienceSurvey::class;

    protected static ?string $modelLabel = 'Goshen experience survey';

    protected static ?string $pluralModelLabel = 'Goshen experience surveys';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Survey')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Retreat edition')
                        ->options(fn (): array => Event::query()
                            ->where(function ($query): void {
                                $query
                                    ->where('settings->module', 'goshen_retreat')
                                    ->orWhere('settings->module', 'goshen-retreat')
                                    ->orWhere('settings->app_module', 'goshen_retreat')
                                    ->orWhere('slug', 'like', 'goshen-retreat%')
                                    ->orWhere('slug', 'like', 'goshen-%')
                                    ->orWhere('name', 'like', '%Goshen Retreat%');
                            })
                            ->orderByDesc('id')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(180),
                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(false),
                    Forms\Components\Toggle::make('allow_all_authenticated_users')
                        ->label('Open to all logged-in app users')
                        ->helperText('When enabled, any signed-in mobile app user can answer this survey even if they have not checked in for the retreat.')
                        ->default(false),
                    Forms\Components\Toggle::make('allow_audio')
                        ->default(true),
                    Forms\Components\Toggle::make('allow_video')
                        ->default(true),
                    Forms\Components\Toggle::make('reminder_enabled')
                        ->label('Send reminders until submitted')
                        ->default(true),
                    Forms\Components\TextInput::make('reminder_interval_minutes')
                        ->numeric()
                        ->minValue(30)
                        ->maxValue(1440)
                        ->default(60),
                    Forms\Components\DateTimePicker::make('opens_at'),
                    Forms\Components\DateTimePicker::make('closes_at'),
                    Forms\Components\Textarea::make('thank_you_message')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Survey questions')
                ->description('Questions appear in this order in the mobile app.')
                ->schema([
                    Forms\Components\Repeater::make('questions')
                        ->relationship()
                        ->itemLabel(fn (array $state): string => filled($state['prompt'] ?? null)
                            ? (string) $state['prompt']
                            : 'Untitled question')
                        ->schema([
                            Forms\Components\TextInput::make('prompt')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Forms\Components\Select::make('type')
                                ->options([
                                    GoshenExperienceQuestion::TYPE_TEXT => 'Short text',
                                    GoshenExperienceQuestion::TYPE_TEXTAREA => 'Long text',
                                    GoshenExperienceQuestion::TYPE_RATING => 'Rating',
                                    GoshenExperienceQuestion::TYPE_CHOICE => 'Single choice',
                                    GoshenExperienceQuestion::TYPE_MULTI_CHOICE => 'Multiple choice',
                                    GoshenExperienceQuestion::TYPE_IMAGE_CHOICE => 'Image choice',
                                    GoshenExperienceQuestion::TYPE_COLOR_CHOICE => 'Colour choice',
                                ])
                                ->default(GoshenExperienceQuestion::TYPE_TEXTAREA)
                                ->live()
                                ->required(),
                            Forms\Components\TagsInput::make('options')
                                ->placeholder('Add option')
                                ->visible(fn ($get): bool => in_array($get('type'), [
                                    GoshenExperienceQuestion::TYPE_CHOICE,
                                    GoshenExperienceQuestion::TYPE_MULTI_CHOICE,
                                ], true)),
                            Forms\Components\Repeater::make('settings.image_options')
                                ->label('Image options')
                                ->helperText('Use this for T-shirt choices or any option where the user should select a visible image.')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('Option label')
                                        ->placeholder('Round neck T-shirt')
                                        ->required()
                                        ->maxLength(120),
                                    Forms\Components\TextInput::make('value')
                                        ->label('Stored value')
                                        ->placeholder('round-neck')
                                        ->helperText('Optional. Leave blank to use the label.')
                                        ->maxLength(120),
                                    Forms\Components\FileUpload::make('image_path')
                                        ->label('Image')
                                        ->disk('public')
                                        ->directory('goshen/experience/options')
                                        ->image()
                                        ->imageEditor()
                                        ->maxSize(5120)
                                        ->downloadable()
                                        ->previewable()
                                        ->required(),
                                ])
                                ->columns(3)
                                ->addActionLabel('Add image option')
                                ->defaultItems(0)
                                ->columnSpanFull()
                                ->visible(fn ($get): bool => $get('type') === GoshenExperienceQuestion::TYPE_IMAGE_CHOICE),
                            Forms\Components\Repeater::make('settings.color_options')
                                ->label('Colour options')
                                ->helperText('Each colour option must have a visible text name such as White, Black, or Navy.')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('Colour name')
                                        ->placeholder('White')
                                        ->required()
                                        ->maxLength(80),
                                    Forms\Components\TextInput::make('value')
                                        ->label('Stored value')
                                        ->placeholder('white')
                                        ->helperText('Optional. Leave blank to use the colour name.')
                                        ->maxLength(80),
                                    Forms\Components\ColorPicker::make('color_hex')
                                        ->label('Colour')
                                        ->default('#ffffff')
                                        ->required(),
                                ])
                                ->columns(3)
                                ->addActionLabel('Add colour option')
                                ->defaultItems(0)
                                ->columnSpanFull()
                                ->visible(fn ($get): bool => $get('type') === GoshenExperienceQuestion::TYPE_COLOR_CHOICE),
                            Forms\Components\TextInput::make('settings.rating_max')
                                ->label('Maximum stars')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(10)
                                ->default(5)
                                ->visible(fn ($get): bool => $get('type') === GoshenExperienceQuestion::TYPE_RATING),
                            Forms\Components\Toggle::make('settings.require_rating_reason')
                                ->label('Require reason for rating')
                                ->default(false)
                                ->visible(fn ($get): bool => $get('type') === GoshenExperienceQuestion::TYPE_RATING),
                            Forms\Components\TextInput::make('settings.rating_reason_label')
                                ->label('Rating reason prompt')
                                ->default('Tell us the reason for your rating')
                                ->maxLength(180)
                                ->visible(fn ($get): bool => $get('type') === GoshenExperienceQuestion::TYPE_RATING),
                            Forms\Components\Toggle::make('is_required'),
                            Forms\Components\TextInput::make('sort_order')
                                ->numeric()
                                ->default(0),
                            Forms\Components\Toggle::make('conditional_logic.enabled')
                                ->label('Show only when another answer matches')
                                ->columnSpanFull()
                                ->default(false),
                            Forms\Components\TextInput::make('conditional_logic.question_id')
                                ->label('Depends on question ID')
                                ->numeric()
                                ->helperText('Use the ID of a previous question. Existing question IDs are visible after the survey is saved.')
                                ->visible(fn ($get): bool => (bool) $get('conditional_logic.enabled')),
                            Forms\Components\Select::make('conditional_logic.operator')
                                ->label('Condition')
                                ->options([
                                    'equals' => 'Equals',
                                    'not_equals' => 'Does not equal',
                                    'contains' => 'Contains',
                                    'not_contains' => 'Does not contain',
                                    'answered' => 'Is answered',
                                    'not_answered' => 'Is not answered',
                                ])
                                ->default('equals')
                                ->native(false)
                                ->visible(fn ($get): bool => (bool) $get('conditional_logic.enabled')),
                            Forms\Components\TextInput::make('conditional_logic.value')
                                ->label('Value to compare')
                                ->maxLength(255)
                                ->helperText('For choice questions, enter the exact option text.')
                                ->visible(fn ($get): bool => (bool) $get('conditional_logic.enabled')),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Retreat edition')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('responses_count')
                    ->counts('responses')
                    ->label('Responses')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opens_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('closes_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                Actions\Action::make('copy')
                    ->label('Copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Copy this survey?')
                    ->modalDescription('A new inactive survey will be created with the same settings and questions. Responses and reminders will not be copied.')
                    ->modalSubmitActionLabel('Copy survey')
                    ->action(function (GoshenExperienceSurvey $record): void {
                        $copy = self::copySurvey($record);

                        Notification::make()
                            ->title('Survey copied')
                            ->body("{$copy->title} is ready for review.")
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\CreateAction::make(),
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenExperienceSurveys::route('/'),
            'create' => Pages\CreateGoshenExperienceSurvey::route('/create'),
            'edit' => Pages\EditGoshenExperienceSurvey::route('/{record}/edit'),
        ];
    }

    public static function copySurvey(GoshenExperienceSurvey $record): GoshenExperienceSurvey
    {
        return DB::transaction(function () use ($record): GoshenExperienceSurvey {
            $record = GoshenExperienceSurvey::query()
                ->with('questions')
                ->findOrFail($record->getKey());

            $copy = $record->replicate(self::surveyCopyExcludedAttributes());
            $copy->title = self::copyTitle($record->title);
            $copy->is_active = false;
            $copy->created_by_id = auth()->id() ?: $record->created_by_id;
            $copy->save();

            foreach ($record->questions as $question) {
                $questionCopy = $question->replicate();
                $questionCopy->survey_id = $copy->id;
                $questionCopy->save();
            }

            return $copy->fresh(['questions']) ?? $copy;
        });
    }

    private static function copyTitle(string $title): string
    {
        $suffix = ' (Copy)';
        $maxLength = 180;

        return str($title)
            ->limit($maxLength - strlen($suffix), '')
            ->append($suffix)
            ->toString();
    }

    private static function surveyCopyExcludedAttributes(): array
    {
        return [
            'responses_count',
        ];
    }
}
