<?php

namespace ChurchTools\DigitalHymnBook\Services;

use Illuminate\Validation\ValidationException;
use JsonException;

class ApprovedHymnDataset
{
    public function read(string $json): array
    {
        $this->require(strlen($json) <= 12 * 1024 * 1024, 'file', 'The JSON file exceeds 12 MiB.');
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => 'The file must contain valid, bounded-depth JSON.']);
        }
        $this->require(is_array($data), 'file', 'The dataset must be an object.');
        $this->require(($data['schema_version'] ?? null) === 1 && ($data['dataset_kind'] ?? null) === 'approved', 'file', 'Import requires approved schema version 1; candidates cannot be imported.');
        $approval = $data['approval'] ?? [];
        $this->text($approval['reviewer'] ?? null, 'approval.reviewer', 190);
        $this->require(is_string($approval['approved_on'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $approval['approved_on']), 'approval.approved_on', 'An editorial approval date is required.');
        $this->require(($approval['preserve_source_wording'] ?? null) === true && ($approval['acknowledge_source_warnings'] ?? null) === true,
            'approval', 'The editorial source-wording and warning decisions are required.');
        $this->require(is_string($approval['candidate_sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $approval['candidate_sha256']), 'approval', 'A reviewed candidate fingerprint is required.');
        $book = $data['book'] ?? [];
        $this->require(is_string($book['code'] ?? null) && preg_match('/^[a-z0-9][a-z0-9-]{1,119}$/', $book['code']), 'book.code', 'A safe stable book code is required.');
        $this->text($book['name'] ?? null, 'book.name', 255);
        $this->text($book['language'] ?? null, 'book.language', 20);
        foreach (['edition' => 255, 'attribution' => 5000, 'copyright_notice' => 5000] as $field => $max) {
            if (isset($book[$field])) {
                $this->text($book[$field], "book.$field", $max);
            }
        }
        $this->require(is_string($data['source']['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $data['source']['sha256']), 'source', 'The PDF source fingerprint is required.');
        $this->require(($book['source_sha256'] ?? null) === $data['source']['sha256'], 'source', 'The book and source fingerprints differ.');
        $this->require(is_array($data['sections'] ?? null) && count($data['sections']) >= 1 && count($data['sections']) <= 200, 'sections', 'The dataset needs 1–200 sections.');
        $sectionCodes = [];
        foreach ($data['sections'] as $i => $section) {
            $this->text($section['code'] ?? null, "sections.$i.code", 120);
            $this->text($section['name'] ?? null, "sections.$i.name", 255);
            $this->require(! isset($sectionCodes[$section['code']]), "sections.$i.code", 'Section codes must be unique.');
            $this->require(is_int($section['order'] ?? null) && $section['order'] === $i + 1, "sections.$i.order", 'Sections must have consecutive display order.');
            $sectionCodes[$section['code']] = true;
        }
        $this->require(is_array($data['hymns'] ?? null) && count($data['hymns']) >= 1 && count($data['hymns']) <= 10000, 'hymns', 'The dataset needs 1–10,000 hymns.');
        $keys = $numbers = [];
        foreach ($data['hymns'] as $i => $hymn) {
            $prefix = "hymns.$i";
            foreach (['source_key' => 160, 'number' => 80, 'title' => 255, 'first_line' => 1000] as $field => $max) {
                $this->text($hymn[$field] ?? null, "$prefix.$field", $max);
            }
            $this->require(! isset($keys[$hymn['source_key']]) && ! isset($numbers[$hymn['number']]), $prefix, 'Source keys and hymn numbers must be unique within the book.');
            $keys[$hymn['source_key']] = $numbers[$hymn['number']] = true;
            $this->require(isset($sectionCodes[$hymn['section_code'] ?? '']), "$prefix.section_code", 'The section must belong to this book.');
            $this->require(is_int($hymn['numeric_order'] ?? null) && $hymn['numeric_order'] >= 1, "$prefix.numeric_order", 'Numeric order must be positive.');
            $this->require(($hymn['review_status'] ?? null) === 'approved_source', "$prefix.review_status", 'Every imported hymn must be editorially approved.');
            foreach (['author', 'tune', 'metre'] as $field) {
                if (isset($hymn[$field])) {
                    $this->text($hymn[$field], "$prefix.$field", 255);
                }
            }
            if (isset($hymn['source_heading'])) {
                $this->text($hymn['source_heading'], "$prefix.source_heading", 5000);
            }
            if (isset($hymn['printed_number'])) {
                $this->text($hymn['printed_number'], "$prefix.printed_number", 80);
            }
            if (isset($hymn['source_pages'])) {
                $pages = $hymn['source_pages'];
                $this->require(is_array($pages) && is_int($pages['start'] ?? null) && is_int($pages['end'] ?? null)
                    && $pages['start'] > 0 && $pages['end'] >= $pages['start'] && $pages['end'] <= 100000,
                    "$prefix.source_pages", 'The source page span is invalid.');
            }
            if (isset($hymn['source_warnings'])) {
                $this->require(is_array($hymn['source_warnings']) && count($hymn['source_warnings']) <= 100,
                    "$prefix.source_warnings", 'Source warnings must be a bounded list.');
            }
            $this->require(is_array($hymn['stanzas'] ?? null) && count($hymn['stanzas']) >= 1 && count($hymn['stanzas']) <= 100, "$prefix.stanzas", 'Each hymn needs 1–100 ordered lyric blocks.');
            foreach ($hymn['stanzas'] as $j => $stanza) {
                $this->require(($stanza['order'] ?? null) === $j + 1 && in_array($stanza['kind'] ?? null, ['verse', 'chorus', 'refrain', 'bridge'], true), "$prefix.stanzas.$j", 'Lyric block order or kind is invalid.');
                $this->text($stanza['text'] ?? null, "$prefix.stanzas.$j.text", 50000);
                if (isset($stanza['label'])) {
                    $this->text($stanza['label'], "$prefix.stanzas.$j.label", 50);
                }
            }
        }

        return $data;
    }

    private function text(mixed $value, string $field, int $max): void
    {
        $this->require(is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max && ! str_contains($value, "\0"), $field, "A nonempty text value of at most $max characters is required.");
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
