<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MergedAccountCredentialService
{
    private bool $syncing = false;

    public function isSyncing(): bool
    {
        return $this->syncing;
    }

    public function validateMobileCredentials(MobileUser $mobileUser, string $plainPassword): bool
    {
        if ($mobileUser->is_deleted || $mobileUser->is_blocked) {
            return false;
        }

        if (filled($mobileUser->password) && Hash::check($plainPassword, $mobileUser->password)) {
            if ($mobileUser->canUseCommunity()) {
                $this->syncAdminFromMobile($mobileUser);
            }

            return true;
        }

        $admin = $this->adminForEmail($mobileUser->email);
        if (! $admin || ! Hash::check($plainPassword, $admin->password ?? '')) {
            return false;
        }

        $this->syncMobileFromAdmin($admin, $mobileUser, verifyMobile: true);

        return true;
    }

    public function validateAdminWithMobileCredentials(User $admin, string $plainPassword): bool
    {
        $mobileUser = $this->mobileForEmail($admin->email);

        if (! $mobileUser || ! $mobileUser->canUseCommunity() || blank($mobileUser->password)) {
            return false;
        }

        if (! Hash::check($plainPassword, $mobileUser->password)) {
            return false;
        }

        $this->syncAdminFromMobile($mobileUser, $admin);

        return true;
    }

    public function syncMobileFromAdmin(User $admin, ?MobileUser $mobileUser = null, bool $verifyMobile = true): void
    {
        if ($this->syncing || blank($admin->email) || blank($admin->password)) {
            return;
        }

        $mobileUser ??= $this->mobileForEmail($admin->email);
        if (! $mobileUser || $mobileUser->is_deleted || $mobileUser->is_blocked) {
            return;
        }

        $attributes = ['password' => $admin->password];

        if ($verifyMobile) {
            $attributes = array_merge($attributes, [
                'is_verified' => true,
                'email_verified_at' => $mobileUser->email_verified_at ?? now(),
                'email_verification_code_hash' => null,
                'email_verification_expires_at' => null,
            ]);
        }

        $this->runSync(fn () => $mobileUser->forceFill($attributes)->saveQuietly());
    }

    public function syncAdminFromMobile(MobileUser $mobileUser, ?User $admin = null): void
    {
        if ($this->syncing || blank($mobileUser->email) || blank($mobileUser->password) || ! $mobileUser->canUseCommunity()) {
            return;
        }

        $admin ??= $this->adminForEmail($mobileUser->email);
        if (! $admin) {
            return;
        }

        $this->runSync(fn () => $admin->forceFill(['password' => $mobileUser->password])->saveQuietly());
    }

    public function mergeForMobile(MobileUser $mobileUser): void
    {
        if ($this->syncing || blank($mobileUser->email) || $mobileUser->is_deleted || $mobileUser->is_blocked) {
            return;
        }

        $admin = $this->adminForEmail($mobileUser->email);
        if (! $admin) {
            return;
        }

        if (filled($mobileUser->password) && $mobileUser->canUseCommunity()) {
            $this->syncAdminFromMobile($mobileUser, $admin);

            return;
        }

        $this->syncMobileFromAdmin($admin, $mobileUser, verifyMobile: true);
    }

    public function adminForEmail(?string $email): ?User
    {
        $email = $this->normalizeEmail($email);
        if ($email === null) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    public function mobileForEmail(?string $email): ?MobileUser
    {
        $email = $this->normalizeEmail($email);
        if ($email === null) {
            return null;
        }

        return MobileUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function runSync(callable $callback): void
    {
        $this->syncing = true;

        try {
            $callback();
        } finally {
            $this->syncing = false;
        }
    }
}
