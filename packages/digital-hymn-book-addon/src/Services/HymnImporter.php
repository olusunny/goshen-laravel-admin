<?php

namespace ChurchTools\DigitalHymnBook\Services;

use App\Models\Addon;
use App\Models\Hymn;
use App\Models\User;
use App\Services\Hymns\HymnAddon;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Models\HymnImport;
use ChurchTools\DigitalHymnBook\Models\HymnSection;
use ChurchTools\DigitalHymnBook\Models\HymnStanza;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HymnImporter
{
    public function __construct(private readonly ApprovedHymnDataset $datasets) {}

    public function preview(string $json, User $actor): array
    {
        HymnAuthorization::ensure($actor);
        $data = $this->datasets->read($json);
        $checksum = hash('sha256', $json);
        $existing = HymnImport::query()->where('file_checksum', $checksum)->first();
        $book = HymnBook::query()->where('code', $data['book']['code'])->first();
        $conflicts = [];
        if ($book && ! $existing) {
            if (! hash_equals((string) $book->source_sha256, $data['source']['sha256'])) {
                $conflicts[] = 'This book uses a different source edition. Use an explicitly reviewed new book code.';
            }
            $modified = Hymn::query()->where('hymn_book_id', $book->id)->whereNull('import_checksum')
                ->whereIn('source_key', array_column($data['hymns'], 'source_key'))->count();
            if ($modified) {
                $conflicts[] = "$modified hymn(s) have later editorial changes. Resolve conflicts before reimporting.";
            }
        }

        return ['book' => $data['book']['name'], 'book_id' => $book?->id, 'checksum' => $checksum, 'hymns' => count($data['hymns']),
            'sections' => count($data['sections']), 'stanzas' => array_sum(array_map(fn ($h) => count($h['stanzas']), $data['hymns'])),
            'already_imported' => $existing !== null, 'conflicts' => $conflicts,
            'warning_count' => (int) ($data['approval']['source_warning_count'] ?? 0)];
    }

    public function import(string $json, User $actor, string $expectedChecksum): array
    {
        $preview = $this->preview($json, $actor);
        if (! hash_equals($preview['checksum'], $expectedChecksum) || $preview['conflicts'] !== []) {
            throw ValidationException::withMessages(['file' => 'The preview changed or has editorial conflicts. Review the JSON again.']);
        }
        if ($preview['already_imported']) {
            return [...$preview, 'imported' => false];
        }
        $data = $this->datasets->read($json);

        return DB::transaction(function () use ($data, $actor, $preview): array {
            $addon = Addon::query()->where('package_key', HymnAddon::PACKAGE_KEY)->lockForUpdate()->first();
            abort_unless($addon?->isActive() && config('addons.enabled', true), 409, 'The hymn add-on was deactivated.');
            $existing = HymnImport::query()->where('file_checksum', $preview['checksum'])->first();
            if ($existing) {
                return [...$preview, 'book_id' => $existing->hymn_book_id, 'already_imported' => true, 'imported' => false];
            }
            $meta = $data['book'];
            $book = HymnBook::query()->firstOrCreate(['code' => $meta['code']], [
                'name' => $meta['name'], 'language' => $meta['language'], 'edition' => $meta['edition'] ?? null,
                'attribution' => $meta['attribution'] ?? null, 'copyright_notice' => $meta['copyright_notice'] ?? null,
                'source_sha256' => $data['source']['sha256'],
            ]);
            $book = HymnBook::query()->lockForUpdate()->findOrFail($book->id);
            if (! hash_equals((string) $book->source_sha256, $data['source']['sha256'])) {
                throw ValidationException::withMessages(['file' => 'The source edition changed after preview. Review the book again.']);
            }
            $sections = [];
            foreach ($data['sections'] as $section) {
                $sections[$section['code']] = HymnSection::query()->updateOrCreate(
                    ['hymn_book_id' => $book->id, 'code' => $section['code']],
                    ['name' => $section['name'], 'sort_order' => $section['order']],
                )->id;
            }
            foreach ($data['hymns'] as $row) {
                $hymn = Hymn::query()->where('hymn_book_id', $book->id)->where('source_key', $row['source_key'])->lockForUpdate()->first();
                if ($hymn && $hymn->import_checksum === null) {
                    throw ValidationException::withMessages(['file' => 'An editor changed a hymn after preview. Review conflicts again.']);
                }
                $hymn ??= new Hymn(['hymn_book_id' => $book->id, 'source_key' => $row['source_key']]);
                $hymn->fill([
                    'title' => $row['title'], 'number' => $row['number'], 'printed_number' => $row['printed_number'] ?? $row['number'],
                    'numeric_order' => $row['numeric_order'], 'hymn_section_id' => $sections[$row['section_code']],
                    'first_line' => $row['first_line'], 'normalized_title' => LyricContent::normalizeTitle($row['title']),
                    'title_initial' => LyricContent::initial($row['title']), 'author' => $row['author'] ?? null,
                    'tune' => $row['tune'] ?? null, 'metre' => $row['metre'] ?? null,
                    'source_heading' => $row['source_heading'] ?? null, 'source_pages' => $row['source_pages'] ?? null,
                    'review_status' => 'approved_source', 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                    'source_warnings' => $row['source_warnings'] ?? [], 'is_published' => false,
                    'content' => LyricContent::html($row['stanzas']), 'import_checksum' => $preview['checksum'],
                ])->save();
                HymnStanza::query()->where('hymn_id', $hymn->id)->delete();
                foreach ($row['stanzas'] as $stanza) {
                    HymnStanza::query()->create(['hymn_id' => $hymn->id, 'sort_order' => $stanza['order'],
                        'kind' => $stanza['kind'], 'label' => $stanza['label'] ?? null, 'text' => $stanza['text']]);
                }
            }
            HymnImport::query()->create(['hymn_book_id' => $book->id, 'file_checksum' => $preview['checksum'],
                'approval' => $data['approval'], 'summary' => $preview, 'imported_by' => $actor->id]);

            return [...$preview, 'book_id' => $book->id, 'imported' => true];
        });
    }
}
