<?php

namespace ChurchTools\DigitalHymnBook\Admin;

use App\Models\User;
use App\Services\Hymns\HymnAddon;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Models\HymnSection;
use ChurchTools\DigitalHymnBook\Services\HymnImporter;
use ChurchTools\DigitalHymnBook\Services\HymnPublisher;
use Filament\Actions\Action;
use Filament\Forms\Components as Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class HymnAdminForms
{
    public function components(): array
    {
        return [
            Section::make('Hymn details')->columns(2)->schema([
                Forms\Select::make('hymn_book_id')->label('Book')->options(fn () => $this->bookOptions())->required()->live(),
                Forms\Select::make('hymn_section_id')->label('Section')->required()
                    ->options(fn (Get $get) => HymnSection::query()->where('hymn_book_id', $get('hymn_book_id'))->orderBy('sort_order')->pluck('name', 'id')->all()),
                Forms\TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Forms\TextInput::make('number')->label('Hymn number')->required()->maxLength(80),
                Forms\TextInput::make('numeric_order')->label('Number order')->numeric()->minValue(1)->required(),
                Forms\TextInput::make('author')->maxLength(255), Forms\TextInput::make('tune')->maxLength(255),
                Forms\TextInput::make('metre')->maxLength(255),
                Forms\Toggle::make('is_published')->label('Include in next publication')->default(false)
                    ->helperText('Changes reach readers when an authorized publisher publishes the book.'),
            ])->columnSpanFull(),
            Section::make('Ordered lyrics')->schema([
                Forms\Repeater::make('stanzas')->label('Verses and choruses')->minItems(1)->maxItems(100)
                    ->reorderableWithButtons()->collapsible()->columns(2)->schema([
                        Forms\Select::make('kind')->options(['verse' => 'Verse', 'chorus' => 'Chorus', 'refrain' => 'Refrain', 'bridge' => 'Bridge'])->default('verse')->required(),
                        Forms\TextInput::make('label')->label('Original verse label')->maxLength(50)->helperText('Source labels are retained; reader navigation follows the order above.'),
                        Forms\Textarea::make('text')->label('Lyrics')->rows(5)->required()->maxLength(50000)->columnSpanFull(),
                    ])->defaultItems(1)->addActionLabel('Add lyric block'),
            ])->columnSpanFull(),
            Section::make('Review and source')->columns(2)->schema([
                Forms\TextInput::make('review_state')->label('Review status')->disabled()->dehydrated(false),
                Forms\TextInput::make('source_reference')->label('Source pages / printed number')->disabled()->dehydrated(false),
                Forms\Textarea::make('source_notes')->label('Source review notes')->rows(4)->disabled()->dehydrated(false)->columnSpanFull(),
            ])->columnSpanFull(),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('importHymnJson')->label('Import reviewed JSON')->icon('heroicon-o-arrow-up-tray')
                ->authorize(fn () => $this->can('manage_hymn'))->modalHeading('Preview and import reviewed hymn JSON')
                ->modalDescription('The preview shows the selected book and counts. Imported records remain drafts until prepared and published.')
                ->modalSubmitActionLabel('Import as drafts')->schema([
                    Forms\FileUpload::make('file')->label('Approved JSON')->storeFiles(false)->required()->live()->maxSize(12288)
                        ->acceptedFileTypes(['application/json', 'text/plain', 'application/octet-stream'])
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $set('expected_checksum', null);
                            $set('import_preview', '');
                            $upload = is_array($state) ? reset($state) : $state;
                            if (! $upload instanceof TemporaryUploadedFile) {
                                return;
                            }
                            $preview = app(HymnImporter::class)->preview($this->uploadedJson($upload), $this->actor());
                            $set('import_preview', $this->importSummary($preview));
                            if ($preview['conflicts'] === []) {
                                $set('expected_checksum', $preview['checksum']);
                            }
                        }),
                    Forms\Textarea::make('import_preview')->label('Import preview')->readOnly()->dehydrated(false)->rows(7),
                    Forms\Hidden::make('expected_checksum')->required(),
                ])->action(function (array $data): void {
                    $result = app(HymnImporter::class)->import($this->uploadedJson($data['file']), $this->actor(), $data['expected_checksum']);
                    Notification::make()->title($result['imported'] ? 'Hymns imported as drafts' : 'This file is already imported')
                        ->body($result['hymns'].' hymns; '.$result['sections'].' sections.')->success()->send();
                }),
            Action::make('prepareHymnBook')->label('Prepare approved imports')->icon('heroicon-o-check-badge')
                ->authorize(fn () => $this->can('manage_hymn'))->requiresConfirmation()
                ->modalDescription('Include the source-approved imported hymns in the next publication. You can edit or exclude individual hymns before publishing.')
                ->schema([Forms\Select::make('book_id')->label('Book')->options(fn () => $this->bookOptions())->required()])
                ->action(function (array $data): void {
                    $count = app(HymnPublisher::class)->prepareImportedBook((int) $data['book_id'], $this->actor());
                    Notification::make()->title('Approved hymns prepared')->body($count.' hymns included in the next publication.')->success()->send();
                }),
            Action::make('publishHymnBook')->label('Publish book')->color('success')->icon('heroicon-o-cloud-arrow-up')
                ->authorize(fn () => $this->can('publish_hymn'))->modalHeading('Review and publish hymn book')
                ->modalSubmitActionLabel('Publish this revision')->schema([
                    Forms\Select::make('book_id')->label('Book')->options(fn () => $this->bookOptions())->required()->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $set('expected_fingerprint', null);
                            if (! $state) {
                                $set('publication_preview', '');

                                return;
                            }
                            try {
                                $preview = app(HymnPublisher::class)->preview((int) $state, $this->actor());
                                $set('publication_preview', $preview['book']."\n".$preview['counts']['hymns'].' hymns; '.$preview['counts']['sections'].' sections; '.$preview['counts']['stanzas']." lyric blocks.\n".$preview['source_warning_count']." retained source notes.\nThis creates a new public lyrics download and search revision.");
                                $set('expected_fingerprint', $preview['fingerprint']);
                            } catch (ValidationException $error) {
                                $set('publication_preview', implode("\n", array_merge(...array_values($error->errors()))));
                            }
                        }),
                    Forms\Textarea::make('publication_preview')->label('Publication preview')->readOnly()->dehydrated(false)->rows(6),
                    Forms\Hidden::make('expected_fingerprint')->required(),
                ])->action(function (array $data): void {
                    $release = app(HymnPublisher::class)->publish((int) $data['book_id'], $this->actor(), $data['expected_fingerprint']);
                    Notification::make()->title('Hymn book published')->body($release->counts['hymns'].' hymns are available to the Flutter apps.')->success()->send();
                }),
        ];
    }

    private function bookOptions(): array
    {
        return HymnBook::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function can(string $permission): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(HymnAddon::class)->canPrepare()
            && ($user->hasRole('super_admin', 'web') || $user->can($permission));
    }

    private function uploadedJson(mixed $upload): string
    {
        if (is_array($upload)) {
            $upload = reset($upload);
        }
        if (! $upload instanceof TemporaryUploadedFile || $upload->getSize() > 12 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Upload a JSON file of at most 12 MiB.']);
        }

        return $upload->get();
    }

    private function importSummary(array $preview): string
    {
        return $preview['book']."\n".$preview['hymns'].' hymns; '.$preview['sections'].' sections; '.$preview['stanzas']." lyric blocks.\n".
            $preview['warning_count']." retained source notes.\n".
            ($preview['already_imported'] ? 'Already imported; later edits will be preserved.' : 'New import; nothing will be published automatically.').
            ($preview['conflicts'] ? "\nConflicts: ".implode("\n", $preview['conflicts']) : '');
    }
}
