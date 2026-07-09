<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\DynamicFormSubmissionResource\Pages;
use App\Models\DynamicFormSubmission;
use BackedEnum;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

class DynamicFormSubmissionResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = DynamicFormSubmission::class;

    protected static ?string $modelLabel = 'form submission';

    protected static ?string $pluralModelLabel = 'form submissions';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Forms';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Submission')
                ->columns(3)
                ->schema([
                    TextEntry::make('dynamicForm.title')->label('Form'),
                    TextEntry::make('reference')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('name')->placeholder('No name'),
                    TextEntry::make('email')->copyable()->placeholder('No email'),
                    TextEntry::make('phone')->copyable()->placeholder('No phone'),
                    TextEntry::make('mobileUser.name')->label('Signed-in user')->placeholder('Visitor'),
                    TextEntry::make('submitted_at')->dateTime()->label('Submitted'),
                ]),
            Section::make('Payment')
                ->columns(4)
                ->schema([
                    TextEntry::make('payment_status')->badge(),
                    TextEntry::make('payment_provider')->placeholder('Not required'),
                    TextEntry::make('amount')->money(fn (DynamicFormSubmission $record): string => $record->currency ?: 'GBP')->placeholder('Free'),
                    TextEntry::make('provider_reference')->copyable()->placeholder('No reference'),
                    TextEntry::make('paid_at')->dateTime()->placeholder('Not paid'),
                    TextEntry::make('wallet_ledger_entry_id')->label('Wallet ledger')->copyable()->placeholder('No wallet entry'),
                ]),
            Section::make('Answers')
                ->schema([
                    TextEntry::make('answers_table')
                        ->label('')
                        ->state(fn (DynamicFormSubmission $record): HtmlString => self::answersTable($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('dynamicForm.title')->label('Form')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('reference')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->placeholder('No name'),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('payment_status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('payment_provider')->placeholder('Free')->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn (DynamicFormSubmission $record): string => $record->currency ?: 'GBP')
                    ->placeholder('Free')
                    ->sortable(),
                Tables\Columns\TextColumn::make('answers_preview')
                    ->label('Answers')
                    ->state(fn (DynamicFormSubmission $record): string => self::answerSummary($record))
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dynamic_form_id')
                    ->relationship('dynamicForm', 'title')
                    ->label('Form'),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        DynamicFormSubmission::PAYMENT_NOT_REQUIRED => 'Not required',
                        DynamicFormSubmission::PAYMENT_PENDING => 'Pending',
                        DynamicFormSubmission::PAYMENT_PAID => 'Paid',
                        DynamicFormSubmission::PAYMENT_FAILED => 'Failed',
                    ]),
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
            'index' => Pages\ListDynamicFormSubmissions::route('/'),
            'view' => Pages\ViewDynamicFormSubmission::route('/{record}'),
        ];
    }

    public static function answerRows(DynamicFormSubmission $record): array
    {
        return collect($record->answers ?: [])
            ->map(function (mixed $answer, mixed $key): array {
                if (! is_array($answer)) {
                    return [
                        'field' => is_string($key) ? $key : 'Field',
                        'answer' => self::formatAnswer($answer),
                        'raw_answer' => $answer,
                    ];
                }

                $rawAnswer = $answer['answer'] ?? null;
                if (is_array($rawAnswer) && ! array_key_exists('key', $rawAnswer)) {
                    $rawAnswer['key'] = (string) ($answer['key'] ?? $key);
                }

                return [
                    'field' => (string) ($answer['label'] ?? $answer['key'] ?? 'Field'),
                    'answer' => self::formatAnswer($rawAnswer),
                    'raw_answer' => $rawAnswer,
                ];
            })
            ->values()
            ->all();
    }

    public static function answerSummary(DynamicFormSubmission $record): string
    {
        $rows = self::answerRows($record);
        if ($rows === []) {
            return 'No answers.';
        }

        return collect($rows)
            ->take(4)
            ->map(fn (array $row): string => $row['field'] . ': ' . $row['answer'])
            ->implode("\n");
    }

    public static function formatAnswer(mixed $answer): string
    {
        if (is_array($answer) && array_key_exists('label', $answer)) {
            return (string) $answer['label'];
        }

        if (is_array($answer) && array_key_exists('file_path', $answer)) {
            return (string) ($answer['original_name'] ?? 'Uploaded file');
        }

        if (is_array($answer)) {
            return collect($answer)->flatten()->map(fn ($item): string => (string) $item)->implode(', ');
        }

        if (is_bool($answer)) {
            return $answer ? 'Yes' : 'No';
        }

        return (string) $answer;
    }

    private static function answersTable(DynamicFormSubmission $record): HtmlString
    {
        $rows = self::answerRows($record);
        if ($rows === []) {
            return new HtmlString('<div>No answers were submitted.</div>');
        }

        $html = '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:.55rem;border-bottom:1px solid #d8e4e8;">Field</th>'
            . '<th style="text-align:left;padding:.55rem;border-bottom:1px solid #d8e4e8;">Answer</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td style="vertical-align:top;padding:.55rem;border-bottom:1px solid #eef2f5;font-weight:700;">' . e($row['field']) . '</td>'
                . '<td style="vertical-align:top;padding:.55rem;border-bottom:1px solid #eef2f5;">' . self::formatAnswerHtml($row['raw_answer'], $record) . '</td>'
                . '</tr>';
        }

        return new HtmlString($html . '</tbody></table>');
    }

    private static function formatAnswerHtml(mixed $answer, DynamicFormSubmission $record): string
    {
        if (is_array($answer) && array_key_exists('image_url', $answer)) {
            $label = e((string) ($answer['label'] ?? 'Selected image'));
            $imageUrl = e((string) $answer['image_url']);

            return '<div style="display:grid;gap:.45rem;max-width:220px;">'
                . '<img src="' . $imageUrl . '" alt="' . $label . '" style="width:100%;max-height:170px;object-fit:contain;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;">'
                . '<strong>' . $label . '</strong>'
                . '</div>';
        }

        if (is_array($answer) && array_key_exists('color_hex', $answer)) {
            $label = e((string) ($answer['label'] ?? 'Selected colour'));
            $color = e((string) ($answer['color_hex'] ?? '#ffffff'));

            return '<span style="align-items:center;display:inline-flex;gap:.5rem;">'
                . '<span style="background:' . $color . ';border:1px solid #94a3b8;border-radius:999px;display:inline-block;height:1.35rem;width:1.35rem;"></span>'
                . '<strong>' . $label . '</strong>'
                . '</span>';
        }

        if (is_array($answer) && array_key_exists('file_path', $answer)) {
            $label = e((string) ($answer['original_name'] ?? 'Uploaded file'));
            $fieldKey = e((string) ($answer['key'] ?? ''));
            $url = e(route('dynamic-form-submissions.files.show', [
                'submission' => $record,
                'field' => $fieldKey,
            ]));

            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        }

        return nl2br(e(self::formatAnswer($answer)));
    }
}
