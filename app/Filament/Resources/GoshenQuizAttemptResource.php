<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenQuizAttemptResource\Pages;
use App\Models\GoshenQuizAttempt;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

class GoshenQuizAttemptResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenQuizAttempt::class;

    protected static ?string $modelLabel = 'Quiz attempt';

    protected static ?string $pluralModelLabel = 'Quiz attempts';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Attempt')
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\TextInput::make('quiz.title')
                        ->label('Quiz')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('mobileUser.name')
                        ->label('Participant')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('status')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('score')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('max_score')
                        ->disabled(),
                    \Filament\Forms\Components\TextInput::make('elapsed_seconds')
                        ->label('Elapsed seconds')
                        ->disabled(),
                    \Filament\Forms\Components\KeyValue::make('answers')
                        ->disabled()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Attempt summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('quiz.title')->label('Quiz')->placeholder('No quiz'),
                    TextEntry::make('mobileUser.name')->label('Participant')->placeholder('Unknown'),
                    TextEntry::make('mobileUser.email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('score')->placeholder('-'),
                    TextEntry::make('max_score')->label('Max score')->placeholder('-'),
                    TextEntry::make('correct_count')->label('Correct'),
                    TextEntry::make('answered_count')->label('Answered'),
                    TextEntry::make('total_questions')->label('Questions'),
                    TextEntry::make('started_at')->dateTime()->placeholder('Not started'),
                    TextEntry::make('due_at')->dateTime()->placeholder('No timer'),
                    TextEntry::make('submitted_at')->dateTime()->placeholder('Not submitted'),
                ]),
            Section::make('Answers')
                ->schema([
                    TextEntry::make('answers_table')
                        ->label('Submitted answers')
                        ->state(fn (GoshenQuizAttempt $record): HtmlString => self::answersTable($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('quiz.title')
                    ->label('Quiz')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mobileUser.name')
                    ->label('Participant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_score')
                    ->label('Max')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('answered_count')
                    ->label('Answered')
                    ->sortable(),
                Tables\Columns\TextColumn::make('elapsed_seconds')
                    ->label('Elapsed')
                    ->formatStateUsing(fn ($state): string => $state === null ? '-' : gmdate('H:i:s', max(0, (int) $state)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('timed_out_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'started' => 'Started',
                        'submitted' => 'Submitted',
                        'timed_out' => 'Timed out',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenQuizAttempts::route('/'),
            'view' => Pages\ViewGoshenQuizAttempt::route('/{record}'),
        ];
    }

    private static function answersTable(GoshenQuizAttempt $record): HtmlString
    {
        $answers = collect($record->answers ?: []);
        if ($answers->isEmpty()) {
            return new HtmlString('<p>No answers were submitted for this attempt.</p>');
        }

        $rows = $answers
            ->map(function (array $answer): string {
                $given = $answer['answer'] ?? null;
                if (is_array($given)) {
                    $given = implode(', ', array_map('strval', $given));
                }

                return '<tr>'
                    . '<td>' . e((string) ($answer['prompt'] ?? 'Question')) . '</td>'
                    . '<td>' . e((string) ($given ?? '')) . '</td>'
                    . '<td>' . e(($answer['is_correct'] ?? null) === null ? '-' : (($answer['is_correct'] ?? false) ? 'Yes' : 'No')) . '</td>'
                    . '<td>' . e((string) ($answer['points_awarded'] ?? 0)) . '</td>'
                    . '</tr>';
            })
            ->implode('');

        return new HtmlString(
            '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">'
            . '<thead><tr><th style="text-align:left">Question</th><th style="text-align:left">Answer</th><th style="text-align:left">Correct</th><th style="text-align:left">Points</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
        );
    }
}
