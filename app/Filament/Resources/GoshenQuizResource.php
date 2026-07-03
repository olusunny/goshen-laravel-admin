<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenQuizResource\Pages;
use App\Models\GoshenQuiz;
use App\Models\GoshenQuizCelebrationMedia;
use App\Models\GoshenQuizQuestion;
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

class GoshenQuizResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenQuiz::class;

    protected static ?string $modelLabel = 'Goshen quiz';

    protected static ?string $pluralModelLabel = 'Goshen quizzes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Quiz')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Retreat edition')
                        ->options(fn (): array => self::goshenEvents())
                        ->searchable()
                        ->helperText('Required when the quiz is only for checked-in Goshen attendees.'),
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(180),
                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('start_instructions')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('completion_message')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(false),
                    Forms\Components\Select::make('audience')
                        ->options([
                            GoshenQuiz::AUDIENCE_ALL_USERS => 'All signed-in app users',
                            GoshenQuiz::AUDIENCE_GOSHEN_CHECKED_IN => 'Checked-in attendees for the selected Goshen edition',
                        ])
                        ->default(GoshenQuiz::AUDIENCE_ALL_USERS)
                        ->required()
                        ->native(false),
                    Forms\Components\DateTimePicker::make('opens_at'),
                    Forms\Components\DateTimePicker::make('closes_at'),
                ]),
            Section::make('Timing and winner rules')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('auto_grade')
                        ->label('Auto grade')
                        ->default(true),
                    Forms\Components\Toggle::make('auto_select_winners')
                        ->label('Select winners automatically')
                        ->helperText('Winners are ranked by highest score, then fastest elapsed time, then first submission.')
                        ->default(true),
                    Forms\Components\Toggle::make('track_timing')
                        ->label('Track timing')
                        ->default(true),
                    Forms\Components\TextInput::make('timer_seconds')
                        ->label('Quiz timer seconds')
                        ->numeric()
                        ->minValue(30)
                        ->maxValue(86400)
                        ->default(300)
                        ->required(),
                    Forms\Components\TextInput::make('winners_count')
                        ->label('Number of winners')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(1)
                        ->required(),
                    Forms\Components\Toggle::make('show_winners_immediately')
                        ->label('Show winners in app immediately')
                        ->default(false),
                ]),
            Section::make('Prize and celebration')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('show_prize')
                        ->label('Show prize')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('prize_label')
                        ->maxLength(180)
                        ->visible(fn ($get): bool => (bool) $get('show_prize')),
                    Forms\Components\Toggle::make('wallet_prize_enabled')
                        ->label('Winner can receive wallet credit')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('wallet_prize_amount')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn ($get): bool => (bool) $get('wallet_prize_enabled')),
                    Forms\Components\TextInput::make('wallet_prize_currency')
                        ->maxLength(3)
                        ->default('GBP')
                        ->visible(fn ($get): bool => (bool) $get('wallet_prize_enabled')),
                    Forms\Components\Toggle::make('celebration_enabled')
                        ->label('Show celebration animation')
                        ->default(false)
                        ->live(),
                    Forms\Components\Select::make('celebration_media_id')
                        ->label('Celebration media')
                        ->options(fn (): array => GoshenQuizCelebrationMedia::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->visible(fn ($get): bool => (bool) $get('celebration_enabled')),
                ]),
            Section::make('Questions')
                ->description('For auto grading, mark the correct option. Participants may submit before answering every question.')
                ->schema([
                    Forms\Components\Repeater::make('questions')
                        ->relationship()
                        ->itemLabel(fn (array $state): string => filled($state['prompt'] ?? null) ? (string) $state['prompt'] : 'Untitled question')
                        ->schema([
                            Forms\Components\TextInput::make('prompt')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Forms\Components\Select::make('type')
                                ->options([
                                    GoshenQuizQuestion::TYPE_SINGLE_CHOICE => 'Single choice',
                                    GoshenQuizQuestion::TYPE_MULTI_CHOICE => 'Multiple choice',
                                    GoshenQuizQuestion::TYPE_TRUE_FALSE => 'True / false',
                                    GoshenQuizQuestion::TYPE_SHORT_TEXT => 'Short text',
                                ])
                                ->default(GoshenQuizQuestion::TYPE_SINGLE_CHOICE)
                                ->live()
                                ->required()
                                ->native(false),
                            Forms\Components\TextInput::make('points')
                                ->numeric()
                                ->minValue(0)
                                ->default(1)
                                ->required(),
                            Forms\Components\Toggle::make('is_required')
                                ->helperText('Shown as a cue only. The app still allows early submission.'),
                            Forms\Components\TextInput::make('sort_order')
                                ->numeric()
                                ->default(0),
                            Forms\Components\Repeater::make('options')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->required()
                                        ->maxLength(160),
                                    Forms\Components\TextInput::make('value')
                                        ->helperText('Optional. Leave blank to use label.')
                                        ->maxLength(160),
                                    Forms\Components\Toggle::make('is_correct')
                                        ->label('Correct'),
                                ])
                                ->columns(3)
                                ->defaultItems(2)
                                ->columnSpanFull()
                                ->visible(fn ($get): bool => in_array($get('type'), [
                                    GoshenQuizQuestion::TYPE_SINGLE_CHOICE,
                                    GoshenQuizQuestion::TYPE_MULTI_CHOICE,
                                    GoshenQuizQuestion::TYPE_TRUE_FALSE,
                                ], true)),
                            Forms\Components\TagsInput::make('settings.accepted_answers')
                                ->label('Accepted text answers')
                                ->helperText('Used only when this short-text question is auto-graded.')
                                ->columnSpanFull()
                                ->visible(fn ($get): bool => $get('type') === GoshenQuizQuestion::TYPE_SHORT_TEXT),
                            Forms\Components\Textarea::make('explanation')
                                ->rows(2)
                                ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('audience')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->headline()->toString()),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Questions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('attempts_count')
                    ->counts('attempts')
                    ->label('Attempts')
                    ->sortable(),
                Tables\Columns\TextColumn::make('winners_count')
                    ->label('Winner slots')
                    ->sortable(),
                Tables\Columns\TextColumn::make('selected_winners_count')
                    ->label('Selected winners')
                    ->state(fn (GoshenQuiz $record): int => $record->winners()->count()),
                Tables\Columns\TextColumn::make('timer_seconds')
                    ->label('Timer')
                    ->formatStateUsing(fn ($state): string => gmdate('H:i:s', max(0, (int) $state)))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('opens_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('closes_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('audience')
                    ->options([
                        GoshenQuiz::AUDIENCE_ALL_USERS => 'All users',
                        GoshenQuiz::AUDIENCE_GOSHEN_CHECKED_IN => 'Checked-in attendees',
                    ]),
            ])
            ->recordActions([
                Actions\Action::make('copy')
                    ->label('Copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Copy this quiz?')
                    ->modalDescription('A new inactive quiz will be created with the same settings and questions. Attempts and winners will not be copied.')
                    ->modalSubmitActionLabel('Copy quiz')
                    ->action(function (GoshenQuiz $record): void {
                        $copy = self::copyQuiz($record);

                        Notification::make()
                            ->title('Quiz copied')
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
            'index' => Pages\ListGoshenQuizzes::route('/'),
            'create' => Pages\CreateGoshenQuiz::route('/create'),
            'edit' => Pages\EditGoshenQuiz::route('/{record}/edit'),
        ];
    }

    private static function goshenEvents(): array
    {
        return Event::query()
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
            ->all();
    }

    public static function copyQuiz(GoshenQuiz $record): GoshenQuiz
    {
        return DB::transaction(function () use ($record): GoshenQuiz {
            $record = GoshenQuiz::query()
                ->with('questions')
                ->findOrFail($record->getKey());

            $copy = $record->replicate(self::quizCopyExcludedAttributes());
            $copy->title = self::copyTitle($record->title);
            $copy->is_active = false;
            $copy->created_by_id = auth()->id() ?: $record->created_by_id;
            $copy->save();

            foreach ($record->questions as $question) {
                $questionCopy = $question->replicate();
                $questionCopy->quiz_id = $copy->id;
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

    private static function quizCopyExcludedAttributes(): array
    {
        return [
            'attempts_count',
            'questions_count',
            'selected_winners_count',
        ];
    }
}
