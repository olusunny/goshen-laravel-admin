<?php

namespace ChurchTools\DigitalHymnBook\Services;

use App\Models\Addon;
use App\Models\Hymn;
use App\Models\User;
use App\Services\Hymns\HymnAddon;
use ChurchTools\DigitalHymnBook\Models\HymnBook;
use ChurchTools\DigitalHymnBook\Models\HymnStanza;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HymnEditor
{
    public function save(array $data, User $actor, ?Hymn $record = null): Hymn
    {
        HymnAuthorization::ensure($actor);
        $data = $this->validate($data, $record);

        return DB::transaction(function () use ($data, $record): Hymn {
            $this->lockActiveAddon();
            HymnBook::query()->lockForUpdate()->findOrFail($data['hymn_book_id']);
            $hymn = $record ? Hymn::query()->lockForUpdate()->findOrFail($record->id) : new Hymn;
            if ($hymn->hymn_book_id !== null && (int) $hymn->hymn_book_id !== (int) $data['hymn_book_id']) {
                throw ValidationException::withMessages(['hymn_book_id' => 'An existing hymn keeps its book and stable identity.']);
            }
            $oldBlocks = $hymn->exists ? $hymn->stanzas->map(fn ($s) => ['kind' => $s->kind, 'label' => $s->label, 'text' => $s->text])->all() : [];
            $blocks = array_values(array_map(fn ($s) => ['kind' => $s['kind'], 'label' => $s['label'] ?? null,
                'text' => trim(str_replace("\r\n", "\n", $s['text']))], $data['stanzas']));
            $firstLine = collect($blocks)->first(fn ($s) => $s['kind'] === 'verse')['text'] ?? $blocks[0]['text'];
            $hymn->fill(collect($data)->only(['title', 'number', 'numeric_order', 'author', 'tune', 'metre',
                'hymn_book_id', 'hymn_section_id', 'is_published'])->all());
            $changed = ! $hymn->exists || $hymn->isDirty(['title', 'number', 'numeric_order', 'author', 'tune', 'metre', 'hymn_section_id']) || $oldBlocks !== $blocks;
            $hymn->first_line = strtok($firstLine, "\n");
            $hymn->normalized_title = LyricContent::normalizeTitle($hymn->title);
            $hymn->title_initial = LyricContent::initial($hymn->title);
            $hymn->source_key ??= $hymn->exists ? 'legacy-'.$hymn->id : 'manual-'.Str::ulid();
            $hymn->content = LyricContent::html($blocks);
            if ($changed) {
                $hymn->review_status = 'draft';
                $hymn->reviewed_by = null;
                $hymn->reviewed_at = null;
                $hymn->import_checksum = null;
            }
            $hymn->save();
            if ($changed) {
                HymnStanza::query()->where('hymn_id', $hymn->id)->delete();
                foreach ($blocks as $i => $block) {
                    HymnStanza::query()->create(['hymn_id' => $hymn->id, 'sort_order' => $i + 1, ...$block]);
                }
            }

            return $hymn->fresh();
        });
    }

    public function review(Hymn $record, User $actor): void
    {
        HymnAuthorization::ensure($actor);
        DB::transaction(function () use ($record, $actor): void {
            $this->lockActiveAddon();
            $hymn = Hymn::query()->lockForUpdate()->findOrFail($record->id);
            $this->validate([...$hymn->toArray(), 'stanzas' => $hymn->stanzas->map(fn ($s) => ['kind' => $s->kind, 'label' => $s->label, 'text' => $s->text])->all()], $hymn);
            $hymn->update(['review_status' => 'reviewed', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });
    }

    private function validate(array $data, ?Hymn $record): array
    {
        $bookId = $data['hymn_book_id'] ?? null;
        $validated = Validator::make($data, [
            'hymn_book_id' => ['required', 'integer', 'exists:hymn_books,id'],
            'hymn_section_id' => ['required', 'integer', Rule::exists('hymn_sections', 'id')->where('hymn_book_id', $bookId)],
            'title' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:80', Rule::unique('hymns', 'number')->where('hymn_book_id', $bookId)->ignore($record?->id)],
            'numeric_order' => ['required', 'integer', 'min:1', 'max:1000000'],
            'author' => ['nullable', 'string', 'max:255'], 'tune' => ['nullable', 'string', 'max:255'], 'metre' => ['nullable', 'string', 'max:255'],
            'is_published' => ['required', 'boolean'], 'stanzas' => ['required', 'array', 'min:1', 'max:100'],
            'stanzas.*.kind' => ['required', Rule::in(['verse', 'chorus', 'refrain', 'bridge'])],
            'stanzas.*.label' => ['nullable', 'string', 'max:50'], 'stanzas.*.text' => ['required', 'string', 'max:50000'],
        ])->validate();
        foreach ($validated['stanzas'] as $index => $stanza) {
            if (trim($stanza['text']) === '') {
                throw ValidationException::withMessages(["stanzas.$index.text" => 'Lyrics cannot be blank.']);
            }
        }

        return $validated;
    }

    private function lockActiveAddon(): void
    {
        $addon = Addon::query()->where('package_key', HymnAddon::PACKAGE_KEY)->lockForUpdate()->first();
        abort_unless($addon?->isActive() && config('addons.enabled', true), 409, 'The hymn add-on was deactivated.');
    }
}
