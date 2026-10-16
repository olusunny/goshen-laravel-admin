<?php

namespace ChurchTools\DigitalHymnBook\Admin;

use App\Contracts\Hymns\HymnAdministration as AdministrationContract;
use App\Models\Hymn;
use App\Models\User;
use ChurchTools\DigitalHymnBook\Services\HymnEditor;

class HymnAdministration implements AdministrationContract
{
    public function __construct(private readonly HymnAdminForms $forms, private readonly HymnEditor $editor) {}

    public function components(): array
    {
        return $this->forms->components();
    }

    public function actions(): array
    {
        return $this->forms->actions();
    }

    public function formData(Hymn $hymn): array
    {
        $blocks = $hymn->stanzas->map(fn ($s) => ['kind' => $s->kind, 'label' => $s->label, 'text' => $s->text])->all();
        if ($blocks === []) {
            $text = html_entity_decode(strip_tags(preg_replace('#<(?:br\s*/?|/(?:p|div|h[1-6]))>#i', "\n", $hymn->content)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $blocks = [['kind' => 'verse', 'label' => '1', 'text' => trim($text)]];
        }
        $pages = $hymn->source_pages;
        $notes = collect($hymn->source_warnings ?? [])->map(fn ($note) => str_replace('_', ' ', $note['code'] ?? 'source note').
            (isset($note['labels']) ? ': '.implode(', ', $note['labels']) : ''))->implode("\n");

        return [...$hymn->toArray(), 'stanzas' => $blocks, 'numeric_order' => $hymn->numeric_order ?? (int) $hymn->number,
            'review_state' => str_replace('_', ' ', $hymn->review_status ?? 'draft'),
            'source_reference' => ($pages ? 'PDF '.$pages['start'].'–'.$pages['end'] : 'Existing/manual hymn').
                ($hymn->printed_number ? '; printed '.$hymn->printed_number : ''),
            'source_notes' => $notes ?: 'No source review notes.'];
    }

    public function save(array $data, User $actor, ?Hymn $hymn = null): Hymn
    {
        return $this->editor->save($data, $actor, $hymn);
    }

    public function review(Hymn $hymn, User $actor): void
    {
        $this->editor->review($hymn, $actor);
    }
}
