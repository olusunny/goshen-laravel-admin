<?php

namespace App\Contracts\Hymns;

/** Stable host boundary: package code is optional and public reads use released snapshots. */
interface HymnCatalog
{
    public function setupComplete(): bool;

    public function publicState(): array;

    public function books(): array;

    public function search(array $filters): array;

    public function hymn(int $id): ?array;

    public function manifest(int $bookId): ?array;

    /** @return array{path: string, filename: string, sha256: string}|null */
    public function download(int $bookId, string $revision): ?array;
}
