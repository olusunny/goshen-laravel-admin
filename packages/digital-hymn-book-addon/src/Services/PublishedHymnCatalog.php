<?php

namespace ChurchTools\DigitalHymnBook\Services;

use App\Contracts\Hymns\HymnCatalog;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Models\HymnPublicationEntry;
use ChurchTools\DigitalHymnBook\Models\HymnRelease;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PublishedHymnCatalog implements HymnCatalog
{
    public function setupComplete(): bool
    {
        return Schema::hasTable('hymn_releases') && Schema::hasTable('hymn_publication_entries');
    }

    public function publicState(): array
    {
        if (! $this->setupComplete()) {
            return ['enabled' => false, 'reason' => 'setup_required', 'books' => []];
        }
        $books = $this->books();
        if (HymnBook::query()->whereNotNull('published_release_id')->count() !== count($books)) {
            // An unavailable/corrupt storage object is an operational failure,
            // not a deliberate deactivation instruction to offline clients.
            throw new RuntimeException('A published hymn release needs recovery before new downloads.');
        }

        return ['enabled' => $books !== [], 'reason' => $books ? 'ready' : 'no_published_book', 'books' => $books];
    }

    public function books(): array
    {
        return HymnBook::query()->whereNotNull('published_release_id')->with('release')->orderBy('id')->get()
            ->filter(function (HymnBook $book): bool {
                $path = $book->release ? $this->releasePath($book->release) : null;

                return $path !== null && hash_equals($book->release->sha256, hash_file('sha256', $path));
            })
            ->map(fn (HymnBook $book): array => $this->releaseManifest($book->release))->values()->all();
    }

    public function manifest(int $bookId): ?array
    {
        $release = $this->currentRelease($bookId);

        return $release && $this->releasePath($release) ? $this->releaseManifest($release) : null;
    }

    public function search(array $filters): array
    {
        $query = $this->entries();
        $term = trim((string) ($filters['q'] ?? ''));
        if (isset($filters['book_id'])) {
            $query->where('hymn_publication_entries.hymn_book_id', (int) $filters['book_id']);
        }
        if (! empty($filters['section'])) {
            $query->where('section_code', $filters['section']);
        }
        if (! empty($filters['letter'])) {
            $query->where('title_initial', mb_strtoupper($filters['letter']));
        }
        if ($term !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term));
            if (preg_match('/^#0*(\d+)$/', $term, $match)) {
                $query->where('number', (string) ((int) $match[1]));
            } elseif (preg_match('/^0*(\d+)$/', $term, $match)) {
                $number = (string) ((int) $match[1]);
                $query->where(fn ($q) => $q->where('number', $number)->orWhereRaw("LOWER(search_text) LIKE ? ESCAPE '!'", ['%'.$literal.'%']))
                    ->orderByRaw('CASE WHEN number = ? THEN 0 ELSE 1 END', [$number]);
            } else {
                $query->whereRaw("LOWER(search_text) LIKE ? ESCAPE '!'", ['%'.$literal.'%'])
                    ->orderByRaw("CASE WHEN normalized_title LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [$literal.'%']);
            }
        }
        $query->orderBy('hymn_publication_entries.hymn_book_id')->orderBy('numeric_order')->orderBy('hymn_id');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 20)));
        $total = (clone $query)->count();
        $items = $query->skip(($page - 1) * $limit)->take($limit)->get()
            ->map(fn (HymnPublicationEntry $entry): array => $this->publicPayload($entry))->all();

        return ['items' => $items, 'page' => $page, 'limit' => $limit, 'total' => $total, 'has_more' => $page * $limit < $total];
    }

    public function hymn(int $id): ?array
    {
        $entry = $this->entries()->where('hymn_id', $id)->first();

        return $entry ? $this->publicPayload($entry) : null;
    }

    public function download(int $bookId, string $revision): ?array
    {
        $release = $this->currentRelease($bookId);
        if (! $release || ! hash_equals($release->revision, $revision)) {
            return null;
        }
        $path = $this->releasePath($release);
        if ($path === null || ! hash_equals($release->sha256, hash_file('sha256', $path))) {
            return null;
        }

        return ['path' => $path, 'filename' => 'hymn-book-'.$bookId.'-'.$revision.'.json', 'sha256' => $release->sha256];
    }

    private function entries(): Builder
    {
        return HymnPublicationEntry::query()->select('hymn_publication_entries.*')
            ->join('hymn_books', function ($join): void {
                $join->on('hymn_books.id', '=', 'hymn_publication_entries.hymn_book_id')
                    ->on('hymn_books.published_release_id', '=', 'hymn_publication_entries.hymn_release_id');
            });
    }

    private function currentRelease(int $bookId): ?HymnRelease
    {
        $book = HymnBook::query()->with('release')->find($bookId);

        return $book?->release;
    }

    private function releaseManifest(HymnRelease $release): array
    {
        return [
            'schema_version' => 1, 'book' => $release->book_metadata, 'sections' => $release->section_metadata,
            'revision' => $release->revision, 'size_bytes' => $release->size_bytes, 'sha256' => $release->sha256,
            'counts' => $release->counts, 'published_at' => $release->created_at->toIso8601String(),
            'download_url' => route('hymns.download', ['bookId' => $release->hymn_book_id, 'revision' => $release->revision]),
        ];
    }

    private function releasePath(HymnRelease $release): ?string
    {
        if (! preg_match('#^hymns/releases/[a-f0-9-]{36}/book\.json$#', $release->file_path)) {
            return null;
        }
        $path = Storage::disk('local')->path($release->file_path);
        $resolved = realpath($path);
        $root = realpath(Storage::disk('local')->path('hymns/releases'));
        if ($resolved === false || $root === false || ! str_starts_with(str_replace('\\', '/', $resolved), rtrim(str_replace('\\', '/', $root), '/').'/')) {
            return null;
        }

        return is_file($resolved) && filesize($resolved) === (int) $release->size_bytes ? $resolved : null;
    }

    private function publicPayload(HymnPublicationEntry $entry): array
    {
        $payload = $entry->payload;
        $payload['content'] = LyricContent::html($payload['stanzas']);
        $payload['thumbnail'] = url('/images/triumphant-hymn-book.png');

        return $payload;
    }
}
