<?php

namespace App\Auth;

use App\Models\User;
use App\Services\MergedAccountCredentialService;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class MergedEmailUserProvider extends EloquentUserProvider
{
    public function validateCredentials(UserContract $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;

        if (! is_string($plain)) {
            return false;
        }

        if (parent::validateCredentials($user, $credentials)) {
            if ($user instanceof User) {
                app(MergedAccountCredentialService::class)->syncMobileFromAdmin($user);
            }

            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return app(MergedAccountCredentialService::class)
            ->validateAdminWithMobileCredentials($user, $plain);
    }
}
