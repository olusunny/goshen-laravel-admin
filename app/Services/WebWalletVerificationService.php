<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use App\Models\WebWalletVerificationChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class WebWalletVerificationService
{
    public function __construct(private readonly DynamicSmtpMailer $mailer)
    {
    }

    public function issue(
        User $admin,
        MobileUser $payer,
        string $purpose,
        array $context,
        ?string $ip,
        ?string $userAgent,
    ): WebWalletVerificationChallenge {
        $email = strtolower(trim((string) $admin->email));
        if ($email !== strtolower(trim((string) $payer->email)) || ! $payer->canUseCommunity()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your linked wallet account could not be verified.',
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = DB::transaction(function () use ($admin, $payer, $email, $purpose, $context, $code, $ip, $userAgent): WebWalletVerificationChallenge {
            User::query()->lockForUpdate()->findOrFail($admin->id);
            $this->assertSendAllowed($admin, $payer, $email, $purpose, $ip);

            return WebWalletVerificationChallenge::query()->create([
                'user_id' => $admin->id,
                'mobile_user_id' => $payer->id,
                'email' => $email,
                'purpose' => $purpose,
                'context' => $context,
                'context_fingerprint' => $this->fingerprint($context),
                'code_hash' => Hash::make($code),
                'status' => 'sending',
                'attempts' => 0,
                'send_count' => 0,
                'created_ip' => $ip,
                'user_agent' => substr((string) $userAgent, 0, 512),
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        try {
            $this->mailer->sendRaw(
                $email,
                'Confirm your Goshen wallet payment',
                "Your wallet verification code is {$code}. It expires in 10 minutes.",
            );
        } catch (Throwable $exception) {
            $challenge->forceFill(['status' => 'delivery_failed'])->save();

            throw new RuntimeException('The wallet verification email could not be sent.', previous: $exception);
        }

        DB::transaction(function () use ($challenge, $admin, $payer, $email, $purpose, $ip): void {
            User::query()->lockForUpdate()->findOrFail($admin->id);

            $matchingPending = WebWalletVerificationChallenge::query()
                ->where('user_id', $admin->id)
                ->where('mobile_user_id', $payer->id)
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->where('status', 'pending');

            $sentAttributes = [
                'send_count' => 1,
                'last_sent_at' => now(),
                'last_sent_ip' => $ip,
            ];

            if ((clone $matchingPending)->where('id', '>', $challenge->id)->exists()) {
                $challenge->forceFill($sentAttributes + [
                    'status' => 'superseded',
                    'superseded_at' => now(),
                ])->save();

                return;
            }

            (clone $matchingPending)
                ->where('id', '<', $challenge->id)
                ->update([
                    'status' => 'superseded',
                    'superseded_at' => now(),
                ]);

            $challenge->forceFill($sentAttributes + [
                'status' => 'pending',
            ])->save();
        });

        return $challenge->fresh();
    }

    public function consume(
        WebWalletVerificationChallenge $challenge,
        User $admin,
        MobileUser $payer,
        string $purpose,
        array $context,
        string $code,
        ?string $ip,
        ?string $userAgent,
    ): WebWalletVerificationChallenge {
        $result = DB::transaction(function () use ($challenge, $admin, $payer, $purpose, $context, $code, $ip, $userAgent): array {
            $locked = WebWalletVerificationChallenge::query()->lockForUpdate()->findOrFail($challenge->id);
            $invalid = $locked->status !== 'pending'
                || $locked->expires_at?->isPast()
                || $locked->user_id !== $admin->id
                || $locked->mobile_user_id !== $payer->id
                || $locked->purpose !== $purpose
                || ! hash_equals($locked->context_fingerprint, $this->fingerprint($context));

            if ($invalid || ! Hash::check(trim($code), $locked->code_hash)) {
                $attempts = $locked->attempts + 1;
                $status = $locked->status;
                if ($status === 'pending') {
                    $status = $attempts >= 5 ? 'locked' : ($locked->expires_at?->isPast() ? 'expired' : 'pending');
                }

                $locked->forceFill([
                    'attempts' => $attempts,
                    'status' => $status,
                    'last_failed_at' => now(),
                    'last_failed_ip' => $ip,
                ])->save();

                return [
                    'challenge' => $locked,
                    'error' => 'The wallet verification code is invalid or expired.',
                ];
            }

            $locked->forceFill([
                'status' => 'consumed',
                'consumed_at' => now(),
                'consumed_ip' => $ip,
                'user_agent' => substr((string) $userAgent, 0, 512),
            ])->save();

            return ['challenge' => $locked, 'error' => null];
        });

        if ($result['error'] !== null) {
            throw ValidationException::withMessages(['wallet_otp' => $result['error']]);
        }

        return $result['challenge']->fresh();
    }

    public function fingerprint(array $context): string
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if (array_is_list($value)) {
                return array_map($normalize, $value);
            }

            ksort($value);

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode($normalize($context), JSON_THROW_ON_ERROR));
    }

    private function assertSendAllowed(
        User $admin,
        MobileUser $payer,
        string $email,
        string $purpose,
        ?string $ip,
    ): void {
        $query = WebWalletVerificationChallenge::query()
            ->where('user_id', $admin->id)
            ->where('mobile_user_id', $payer->id)
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->where('created_ip', $ip);

        $latest = (clone $query)->latest('created_at')->first();
        if ($latest?->created_at?->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages([
                'wallet_otp' => 'Wait 60 seconds before requesting another code.',
            ]);
        }

        if ((clone $query)->where('created_at', '>=', now()->subHour())->count() >= 5) {
            throw ValidationException::withMessages([
                'wallet_otp' => 'The hourly wallet verification email limit has been reached.',
            ]);
        }
    }
}
