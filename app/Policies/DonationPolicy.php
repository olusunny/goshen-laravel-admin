<?php

namespace App\Policies;

use App\Filament\Resources\DonationResource;
use App\Models\Donation;
use App\Models\User;
use App\Support\AdminPermissions;

class DonationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageDonations($user);
    }

    public function view(User $user, Donation $donation): bool
    {
        return $this->canManageDonations($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageDonations($user);
    }

    public function update(User $user, Donation $donation): bool
    {
        return $this->canManageDonations($user)
            && ! $donation->isCompleted();
    }

    public function delete(User $user, Donation $donation): bool
    {
        return $this->canManageDonations($user)
            && ! $donation->isCompleted();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Donation $donation): bool
    {
        return false;
    }

    public function forceDelete(User $user, Donation $donation): bool
    {
        return false;
    }

    private function canManageDonations(User $user): bool
    {
        return $user->hasRole('super_admin', 'web')
            || $user->can(AdminPermissions::resourcePermission(DonationResource::class));
    }
}
