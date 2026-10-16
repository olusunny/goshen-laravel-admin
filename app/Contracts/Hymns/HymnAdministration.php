<?php

namespace App\Contracts\Hymns;

use App\Models\Hymn;
use App\Models\User;

interface HymnAdministration
{
    public function components(): array;

    public function actions(): array;

    public function formData(Hymn $hymn): array;

    public function save(array $data, User $actor, ?Hymn $hymn = null): Hymn;

    public function review(Hymn $hymn, User $actor): void;
}
