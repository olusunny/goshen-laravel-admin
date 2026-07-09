<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenExperienceResponseResource\Pages;
use App\Models\GoshenExperienceResponse;
use BackedEnum;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use UnitEnum;

class GoshenExperienceResponseResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenExperienceResponse::class;

    protected static ?string $modelLabel = 'Goshen experience response';

    protected static ?string $pluralModelLabel = 'Goshen experience responses';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Attendee')
                ->columns(3)
                ->schema([
                    TextEntry::make('survey.title')->label('Survey'),
                    TextEntry::make('event.name')->label('Retreat edition'),
                    TextEntry::make('mobileUser.name')->label('Attendee')->placeholder('Unknown'),
                    TextEntry::make('mobileUser.email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('mobileUser.gender')->label('Gender')->placeholder('Not set'),
                    TextEntry::make('mobileUser.country_of_residence')->label('Country')->placeholder('Not set'),
                    TextEntry::make('booking.public_id')->label('Booking')->copyable()->placeholder('No booking'),
                    TextEntry::make('ticket.formatted_number')->label('Ticket')->copyable()->placeholder('No ticket'),
                    TextEntry::make('submitted_at')->dateTime()->label('Submitted'),
                ]),
            Section::make('Experience')
                ->schema([
                    TextEntry::make('story')->label('Story')->placeholder('No written story.')->columnSpanFull(),
                    TextEntry::make('answers')
                        ->label('Survey answers')
                        ->state(fn (GoshenExperienceResponse $record): HtmlString => self::answersTable($record))
                        ->html()
                        ->columnSpanFull(),
                    TextEntry::make('audio_player')
                        ->label('Audio response')
                        ->state(fn (GoshenExperienceResponse $record): HtmlString => self::mediaPlayer($record->audio_path, 'audio'))
                        ->html()
                        ->columnSpanFull(),
                    TextEntry::make('audio_duration_seconds')->suffix(' sec')->placeholder('No audio duration'),
                    TextEntry::make('video_player')
                        ->label('Video response')
                        ->state(fn (GoshenExperienceResponse $record): HtmlString => self::mediaPlayer($record->video_path, 'video'))
                        ->html()
                        ->columnSpanFull(),
                    TextEntry::make('video_duration_seconds')->suffix(' sec')->placeholder('No video duration'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('survey.title')->label('Survey')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('event.name')->label('Retreat edition')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('mobileUser.name')->label('Attendee')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('mobileUser.email')->label('Email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mobileUser.country_of_residence')->label('Country')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('audio_link')
                    ->label('Audio')
                    ->state(fn (GoshenExperienceResponse $record): string => filled($record->audio_path) ? 'Play audio' : 'No audio')
                    ->url(fn (GoshenExperienceResponse $record): ?string => self::publicMediaUrl($record->audio_path), shouldOpenInNewTab: true)
                    ->badge()
                    ->color(fn (GoshenExperienceResponse $record): string => filled($record->audio_path) ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('video_link')
                    ->label('Video')
                    ->state(fn (GoshenExperienceResponse $record): string => filled($record->video_path) ? 'Play video' : 'No video')
                    ->url(fn (GoshenExperienceResponse $record): ?string => self::publicMediaUrl($record->video_path), shouldOpenInNewTab: true)
                    ->badge()
                    ->color(fn (GoshenExperienceResponse $record): string => filled($record->video_path) ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('answers_preview')
                    ->label('Questions and answers')
                    ->state(fn (GoshenExperienceResponse $record): string => self::answerSummary($record))
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('survey_id')
                    ->relationship('survey', 'title')
                    ->label('Survey'),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
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
            'index' => Pages\ListGoshenExperienceResponses::route('/'),
            'view' => Pages\ViewGoshenExperienceResponse::route('/{record}'),
        ];
    }

    public static function answerRows(GoshenExperienceResponse $record): array
    {
        return collect($record->answers ?: [])
            ->map(function (mixed $answer, mixed $key): array {
                if (! is_array($answer)) {
                    return [
                        'question' => is_string($key) ? $key : 'Question',
                        'type' => '',
                        'answer' => self::formatAnswer($answer),
                        'raw_answer' => $answer,
                    ];
                }

                return [
                    'question' => (string) ($answer['prompt'] ?? 'Question'),
                    'type' => (string) ($answer['type'] ?? ''),
                    'answer' => self::formatAnswer($answer['answer'] ?? null),
                    'raw_answer' => $answer['answer'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public static function answerSummary(GoshenExperienceResponse $record): string
    {
        $rows = self::answerRows($record);
        if ($rows === []) {
            return 'No survey answers.';
        }

        return collect($rows)
            ->map(fn (array $row): string => $row['question'] . ': ' . $row['answer'])
            ->implode("\n");
    }

    public static function formatAnswer(mixed $answer): string
    {
        if (is_array($answer) && array_key_exists('rating', $answer)) {
            $rating = (int) ($answer['rating'] ?? 0);
            $max = (int) ($answer['max'] ?? 5);
            $reason = trim((string) ($answer['reason'] ?? ''));

            return trim($rating . '/' . $max . ' stars' . ($reason !== '' ? ' - ' . $reason : ''));
        }

        if (is_array($answer) && array_key_exists('label', $answer)) {
            return (string) $answer['label'];
        }

        if (is_array($answer)) {
            return collect($answer)->flatten()->map(fn ($item): string => (string) $item)->implode(', ');
        }

        return (string) $answer;
    }

    private static function answersTable(GoshenExperienceResponse $record): HtmlString
    {
        $rows = self::answerRows($record);
        if ($rows === []) {
            return new HtmlString('<div>No survey answers were submitted.</div>');
        }

        $html = '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:.55rem;border-bottom:1px solid #d8e4e8;">Question</th>'
            . '<th style="text-align:left;padding:.55rem;border-bottom:1px solid #d8e4e8;">Answer</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td style="vertical-align:top;padding:.55rem;border-bottom:1px solid #eef2f5;font-weight:700;">' . e($row['question']) . '</td>'
                . '<td style="vertical-align:top;padding:.55rem;border-bottom:1px solid #eef2f5;">' . self::formatAnswerHtml($row['raw_answer']) . '</td>'
                . '</tr>';
        }

        return new HtmlString($html . '</tbody></table>');
    }

    private static function formatAnswerHtml(mixed $answer): string
    {
        if (is_array($answer) && array_key_exists('image_path', $answer)) {
            $label = e((string) ($answer['label'] ?? 'Selected image'));
            $imageUrl = self::publicMediaUrl((string) ($answer['image_path'] ?? ''));

            if ($imageUrl !== null) {
                return '<div style="display:grid;gap:.45rem;max-width:220px;">'
                    . '<img src="' . e($imageUrl) . '" alt="' . $label . '" style="width:100%;max-height:170px;object-fit:contain;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;">'
                    . '<strong>' . $label . '</strong>'
                    . '</div>';
            }
        }

        if (is_array($answer) && array_key_exists('color_hex', $answer)) {
            $label = e((string) ($answer['label'] ?? 'Selected colour'));
            $color = e(self::normalizeColorHex($answer['color_hex'] ?? null));

            return '<span style="align-items:center;display:inline-flex;gap:.5rem;">'
                . '<span style="background:' . $color . ';border:1px solid #94a3b8;border-radius:999px;display:inline-block;height:1.35rem;width:1.35rem;"></span>'
                . '<strong>' . $label . '</strong>'
                . '</span>';
        }

        return nl2br(e(self::formatAnswer($answer)));
    }

    private static function mediaPlayer(?string $path, string $type): HtmlString
    {
        $url = self::publicMediaUrl($path);
        $label = $type === 'video' ? 'video' : 'audio';

        if ($url === null) {
            return new HtmlString('<div style="color:#64748b;">No ' . $label . ' response was submitted.</div>');
        }

        $escapedUrl = e($url);
        $media = $type === 'video'
            ? '<video controls preload="metadata" style="width:100%;max-width:640px;border-radius:8px;background:#0f172a;"><source src="' . $escapedUrl . '"></video>'
            : '<audio controls preload="metadata" style="width:100%;max-width:640px;"><source src="' . $escapedUrl . '"></audio>';

        return new HtmlString(
            '<div style="display:grid;gap:.5rem;">'
            . $media
            . '<a href="' . $escapedUrl . '" target="_blank" rel="noopener noreferrer" style="align-items:center;background:#f59e0b;border-radius:8px;color:#111827;display:inline-flex;font-weight:700;justify-content:center;max-width:13rem;padding:.625rem 1rem;text-decoration:none;">Open ' . $label . '</a>'
            . '</div>'
        );
    }

    private static function publicMediaUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private static function normalizeColorHex(mixed $color): string
    {
        $color = trim((string) $color);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $color) === 1) {
            return '#' . strtolower(ltrim($color, '#'));
        }

        return '#ffffff';
    }
}
