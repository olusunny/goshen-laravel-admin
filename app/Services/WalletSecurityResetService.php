<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use App\Models\WalletSecurityResetRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletSecurityResetService
{
    private const MAX_ADMIN_RESETS_PER_HOUR = 5;

    public function __construct(private readonly GoshenRetreatNotificationService $notifications) {}

    public function requestReset(
        MobileUser $mobileUser,
        ?User $admin,
        string $verificationMethod,
        string $verificationNotes,
        ?string $requestIp = null,
        ?string $requestUserAgent = null,
    ): WalletSecurityResetRequest {
        $verificationMethod = trim($verificationMethod);
        $verificationNotes = trim($verificationNotes);

        if ($verificationMethod === '' || $verificationNotes === '') {
            throw new RuntimeException('Record the verification method and notes before resetting wallet security.');
        }

        $this->enforceAdminResetLimit($admin);

        $reset = DB::transaction(function () use ($mobileUser, $admin, $verificationMethod, $verificationNotes, $requestIp, $requestUserAgent): WalletSecurityResetRequest {
            $lockedUser = MobileUser::query()
                ->whereKey($mobileUser->id)
                ->lockForUpdate()
                ->firstOrFail();

            $pendingResetExists = WalletSecurityResetRequest::query()
                ->where('mobile_user_id', $lockedUser->id)
                ->where('status', WalletSecurityResetRequest::STATUS_PENDING)
                ->exists();

            if ($lockedUser->wallet_security_reset_required || $pendingResetExists) {
                throw new RuntimeException('This member already has a pending wallet security reset.');
            }

            $now = now();

            $lockedUser->forceFill([
                'api_token_hash' => null,
                'wallet_security_reset_required' => true,
                'wallet_security_reset_requested_at' => $now,
                'wallet_security_reset_acknowledged_at' => null,
            ])->save();

            return WalletSecurityResetRequest::query()->create([
                'mobile_user_id' => $lockedUser->id,
                'admin_user_id' => $admin?->id,
                'status' => WalletSecurityResetRequest::STATUS_PENDING,
                'verification_method' => $verificationMethod,
                'verification_notes' => $verificationNotes,
                'invalidated_mobile_session' => true,
                'notified_user' => false,
                'requested_at' => $now,
                'metadata' => [
                    'request_ip' => $requestIp,
                    'request_user_agent' => $requestUserAgent,
                ],
            ]);
        });

        $notified = $this->notifyUser($reset->fresh(['mobileUser']));
        $reset->forceFill(['notified_user' => $notified])->save();

        return $reset->fresh();
    }

    public function acknowledgePendingReset(
        MobileUser $mobileUser,
        ?string $ip = null,
        ?string $userAgent = null,
    ): ?WalletSecurityResetRequest {
        return DB::transaction(function () use ($mobileUser, $ip, $userAgent): ?WalletSecurityResetRequest {
            $lockedUser = MobileUser::query()
                ->whereKey($mobileUser->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reset = WalletSecurityResetRequest::query()
                ->where('mobile_user_id', $lockedUser->id)
                ->where('status', WalletSecurityResetRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->latest('requested_at')
                ->first();

            if (! $reset) {
                return null;
            }

            $now = now();

            $reset->forceFill([
                'status' => WalletSecurityResetRequest::STATUS_ACKNOWLEDGED,
                'acknowledged_at' => $now,
                'acknowledged_ip' => $ip,
                'acknowledged_user_agent' => $userAgent ? substr($userAgent, 0, 512) : null,
            ])->save();

            $lockedUser->forceFill([
                'wallet_security_reset_required' => false,
                'wallet_security_reset_acknowledged_at' => $now,
            ])->save();

            return $reset->fresh();
        });
    }

    public function pendingResetFor(MobileUser $mobileUser): ?WalletSecurityResetRequest
    {
        return WalletSecurityResetRequest::query()
            ->where('mobile_user_id', $mobileUser->id)
            ->where('status', WalletSecurityResetRequest::STATUS_PENDING)
            ->latest('requested_at')
            ->first();
    }

    public function hasPendingReset(MobileUser $mobileUser): bool
    {
        return (bool) $mobileUser->wallet_security_reset_required
            || $this->pendingResetFor($mobileUser) !== null;
    }

    public function assertWalletActionsAllowed(MobileUser $mobileUser): void
    {
        if ($this->hasPendingReset($mobileUser)) {
            throw new RuntimeException('Wallet security has been reset by support. Please sign in again and create a new wallet PIN before using wallet funds.');
        }
    }

    public function statusPayload(MobileUser $mobileUser): array
    {
        $reset = $this->pendingResetFor($mobileUser);

        return [
            'reset_required' => $reset !== null || (bool) $mobileUser->wallet_security_reset_required,
            'requested_at' => $reset?->requested_at?->toIso8601String()
                ?? $mobileUser->wallet_security_reset_requested_at?->toIso8601String(),
            'acknowledged_at' => $mobileUser->wallet_security_reset_acknowledged_at?->toIso8601String(),
            'message' => $reset !== null || (bool) $mobileUser->wallet_security_reset_required
                ? 'Support has approved a wallet security reset. Please create a new wallet PIN on this device.'
                : null,
        ];
    }

    private function enforceAdminResetLimit(?User $admin): void
    {
        if (! $admin || $admin->hasRole('super_admin')) {
            return;
        }

        $recentCount = WalletSecurityResetRequest::query()
            ->where('admin_user_id', $admin->id)
            ->where('requested_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= self::MAX_ADMIN_RESETS_PER_HOUR) {
            throw new RuntimeException('This admin has reached the wallet reset limit for the current hour.');
        }
    }

    private function notifyUser(WalletSecurityResetRequest $reset): bool
    {
        $user = $reset->mobileUser;
        if (! $user) {
            return false;
        }

        try {
            $this->notifications->notifyUser(
                $user,
                'Wallet security reset approved',
                "Hello {$user->name}, support has verified your account and approved a wallet security reset. Please sign in again, open My Wallet, and create a new wallet PIN on your device. Your old wallet PIN was not viewed or recovered.",
                'giving',
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
