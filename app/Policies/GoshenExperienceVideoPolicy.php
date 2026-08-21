<?php

namespace App\Policies;

use App\Filament\Resources\GoshenExperienceVideoResource;
use App\Models\GoshenExperienceVideo;
use App\Models\User;
use App\Support\AdminPermissions;

class GoshenExperienceVideoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, GoshenExperienceVideo $video): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, GoshenExperienceVideo $video): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, GoshenExperienceVideo $video): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole('super_admin', 'web')
            || $user->can(AdminPermissions::resourcePermission(GoshenExperienceVideoResource::class));
    }
}
