<?php

namespace ChurchTools\DigitalHymnBook\Services;

use App\Models\Addon;
use App\Models\Hymn;
use App\Models\User;
use App\Services\Hymns\HymnAddon;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Models\HymnPublicationEntry;
use ChurchTools\DigitalHymnBook\Models\HymnRelease;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class HymnPublisher
{
    /** Prepare approved imported drafts; this does not change the public release. */
    public function prepareImportedBook(int $bookId, User $actor): int
    {
        HymnAuthorization::ensure($actor);

        return DB::transaction(function () use ($bookId, $actor): int {
            $this->lockActiveAddon();
            HymnBook::query()->lockForUpdate()->findOrFail($bookId);
            $count = Hymn::query()->where('hymn_book_id', $bookId)->where('review_status', 'approved_source')
                ->whereNotNull('import_checksum')->where('is_published', false)->update([
                    'is_published' => true, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
                ]);
            Log::info('Approved source hymns prepared for publication.', ['book_id' => $bookId, 'actor_id' => $actor->id, 'hymns' => $count]);

            return $count;
        });
    }

    public function preview(int $bookId, User $actor): array
    {
        HymnAuthorization::ensure($actor, 'publish_hymn');
        $snapshot = $this->snapshot(HymnBook::query()->findOrFail($bookId));

        return $this->summary($snapshot);
    }

    public function publish(int $bookId, User $actor, string $expectedFingerprint): HymnRelease
    {
        HymnAuthorization::ensure($actor, 'publish_hymn');
        $createdPath = null;
        try {
            return DB::transaction(function () use ($bookId, $actor, $expectedFingerprint, &$createdPath): HymnRelease {
                $this->lockActiveAddon();
                $book = HymnBook::query()->lockForUpdate()->findOrFail($bookId);
                $snapshot = $this->snapshot($book, lock: true);
                $summary = $this->summary($snapshot);
                if (! hash_equals($summary['fingerprint'], $expectedFingerprint)) {
                    throw ValidationException::withMessages(['publication' => 'The book changed after preview. Review the publication again.']);
                }
                $revision = (string) Str::uuid();
                $dataset = ['schema_version' => 1, 'revision' => $revision,
                    'book' => $snapshot['book'], 'sections' => $snapshot['sections'], 'counts' => $summary['counts'],
                    'hymns' => $snapshot['hymns']];
                $json = json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $createdPath = 'hymns/releases/'.$revision.'/book.json';
                $disk = Storage::disk('local');
                if (! $disk->put($createdPath, $json) || ! hash_equals(hash('sha256', $json), hash_file('sha256', $disk->path($createdPath)))) {
                    throw ValidationException::withMessages(['publication' => 'Could not safely save the downloadable release. The previous release remains active.']);
                }
                $release = HymnRelease::query()->create([
                    'hymn_book_id' => $bookId, 'revision' => $revision, 'file_path' => $createdPath,
                    'sha256' => hash('sha256', $json), 'size_bytes' => strlen($json), 'counts' => $summary['counts'],
                    'book_metadata' => $snapshot['book'], 'section_metadata' => $snapshot['sections'], 'published_by' => $actor->id,
                ]);
                $entries = [];
                foreach ($snapshot['hymns'] as $hymn) {
                    $entries[] = ['hymn_release_id' => $release->id, 'hymn_id' => $hymn['id'], 'hymn_book_id' => $bookId,
                        'number' => $hymn['number'], 'numeric_order' => $hymn['numeric_order'], 'normalized_title' => $hymn['normalized_title'],
                        'title_initial' => $hymn['initial'], 'section_code' => $hymn['section_code'],
                        'search_text' => mb_strtolower($hymn['normalized_title'].' '.$hymn['title'].' '.$hymn['first_line'].' '.implode(' ', array_column($hymn['stanzas'], 'text'))),
                        'payload' => json_encode($hymn, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
                }
                foreach (array_chunk($entries, 100) as $chunk) {
                    HymnPublicationEntry::query()->insert($chunk);
                }
                // The release pointer changes only after file integrity and all
                // immutable search entries are present in this same transaction.
                $book->update(['published_release_id' => $release->id]);

                return $release;
            });
        } catch (Throwable $error) {
            if ($createdPath !== null) {
                try {
                    if (! HymnRelease::query()->where('file_path', $createdPath)->exists()) {
                        Storage::disk('local')->delete($createdPath);
                    }
                } catch (Throwable) {
                    // An uncertain commit outcome must never delete a possibly
                    // published file. An unreferenced private file is recoverable.
                    Log::warning('Hymn release file retained after an uncertain transaction result.', ['path' => $createdPath]);
                }
            }
            throw $error;
        }
    }

    private function lockActiveAddon(): void
    {
        $addon = Addon::query()->where('package_key', HymnAddon::PACKAGE_KEY)->lockForUpdate()->first();
        abort_unless($addon?->isActive() && config('addons.enabled', true), 409, 'The hymn add-on was deactivated.');
    }

    private function snapshot(HymnBook $book, bool $lock = false): array
    {
        $query = Hymn::query()->where('hymn_book_id', $book->id)->where('is_published', true)
            ->with(['stanzas', 'section'])->orderBy('numeric_order')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $records = $query->get();
        if ($records->isEmpty()) {
            throw ValidationException::withMessages(['publication' => 'Prepare and review hymns before publishing this book.']);
        }
        $hymns = [];
        $warnings = 0;
        foreach ($records as $record) {
            if (! in_array($record->review_status, ['approved_source', 'reviewed'], true)
                || ! $record->section || $record->section->hymn_book_id !== $book->id || $record->stanzas->isEmpty()) {
                throw ValidationException::withMessages(['publication' => 'Every included hymn needs reviewed lyrics and a section in this book.']);
            }
            $verse = 0;
            $blocks = $record->stanzas->map(function ($stanza, int $index) use (&$verse): array {
                if (trim($stanza->text) === '' || ! in_array($stanza->kind, ['verse', 'chorus', 'refrain', 'bridge'], true)) {
                    throw ValidationException::withMessages(['publication' => 'An included lyric block is empty or invalid.']);
                }

                return ['order' => $index + 1, 'kind' => $stanza->kind, 'label' => $stanza->label,
                    'verse_number' => $stanza->kind === 'verse' ? ++$verse : null, 'text' => $stanza->text];
            })->all();
            $hymns[] = ['id' => $record->id, 'book_id' => $book->id, 'number' => (string) $record->number,
                'printed_number' => $record->printed_number, 'numeric_order' => $record->numeric_order,
                'title' => $record->title, 'first_line' => $record->first_line,
                'normalized_title' => LyricContent::normalizeTitle($record->title), 'initial' => LyricContent::initial($record->title),
                'section_id' => $record->section->id, 'section_code' => $record->section->code, 'section_title' => $record->section->name,
                'author' => $record->author, 'tune' => $record->tune, 'metre' => $record->metre, 'stanzas' => $blocks];
            $warnings += count($record->source_warnings ?? []);
        }
        $sections = $book->sections()->orderBy('sort_order')->get()->map(fn ($section): array => [
            'id' => $section->id, 'code' => $section->code, 'name' => $section->name, 'order' => $section->sort_order,
        ])->all();

        return ['book' => ['id' => $book->id, 'code' => $book->code, 'name' => $book->name, 'language' => $book->language,
            'edition' => $book->edition, 'attribution' => $book->attribution, 'copyright_notice' => $book->copyright_notice],
            'sections' => $sections, 'hymns' => $hymns, 'source_warning_count' => $warnings];
    }

    private function summary(array $snapshot): array
    {
        return ['book' => $snapshot['book']['name'], 'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'counts' => ['books' => 1, 'sections' => count($snapshot['sections']), 'hymns' => count($snapshot['hymns']),
                'stanzas' => array_sum(array_map(fn ($h) => count($h['stanzas']), $snapshot['hymns']))],
            'source_warning_count' => $snapshot['source_warning_count']];
    }
}
