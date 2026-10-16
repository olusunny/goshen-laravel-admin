<?php

namespace ChurchTools\DigitalHymnBook\Services;

use App\Models\User;
use App\Services\Hymns\HymnAddon;

class HymnAuthorization
{
    public static function ensure(User $actor, string $permission = 'manage_hymn'): void
    {
        abort_unless($actor->hasRole('super_admin', 'web') || $actor->can($permission), 403);
        abort_unless(app(HymnAddon::class)->canPrepare(), 409, 'The hymn add-on is not active and ready for editing.');
    }
}
