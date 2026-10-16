<?php

namespace ChurchTools\DigitalHymnBook\Services;

class LyricContent
{
    public static function normalizeTitle(string $title): string
    {
        $words = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title) ?? $title;

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $words) ?? $words));
    }

    public static function initial(string $title): string
    {
        $initial = mb_strtoupper(mb_substr(self::normalizeTitle($title), 0, 1));

        return preg_match('/^[A-Z]$/', $initial) ? $initial : '#';
    }

    public static function html(array $stanzas): string
    {
        return collect($stanzas)->map(function (array $stanza, int $index): string {
            $kind = $stanza['kind'] ?? 'verse';
            $label = $kind === 'verse' ? 'Verse '.($stanza['verse_number'] ?? $stanza['label'] ?? ($index + 1)) : ucfirst($kind);

            return '<p><strong>'.e($label).'</strong><br>'.nl2br(e($stanza['text']), false).'</p>';
        })->implode("\n");
    }
}
