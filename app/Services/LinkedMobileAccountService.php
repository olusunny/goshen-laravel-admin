<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LinkedMobileAccountService
{
    public function forAdmin(User $admin): ?MobileUser
    {
        $email = strtolower(trim((string) $admin->email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || blank($admin->password)) {
            return null;
        }

        return DB::transaction(function () use ($admin, $email): MobileUser {
            $mobile = MobileUser::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if (! $mobile) {
                $mobile = MobileUser::query()->create([
                    'name' => $admin->name,
                    'email' => $email,
                    'password' => $admin->password,
                    'login_type' => 'linked_web_admin',
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'is_blocked' => false,
                    'is_deleted' => false,
                ]);
            }

            return $mobile;
        });
    }
}
