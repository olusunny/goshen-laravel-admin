<?php

namespace App\Services;

use App\Models\MobileUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MembershipProfileService
{
    public const STATUS_CHANGE_COOLDOWN_DAYS = 30;

    public function isVisitor(MobileUser $user): bool
    {
        return $this->normalizeMemberType($user->member_type) === 'visitor';
    }

    public function normalizeMemberType(mixed $value): ?string
    {
        $value = str((string) $value)->trim()->lower()->toString();

        return in_array($value, ['church_member', 'visitor'], true) ? $value : null;
    }

    public function recordStatusChange(MobileUser $user): void
    {
        if (! $user->exists || ! $user->isDirty('member_type') || ! Schema::hasColumn('mobile_users', 'membership_status_changed_at')) {
            return;
        }

        $previous = $this->normalizeMemberType($user->getOriginal('member_type'));
        $current = $this->normalizeMemberType($user->member_type);

        if ($previous === null || $current === null || $previous === $current) {
            return;
        }

        $availableAt = $this->statusChangeAvailableAt($user);
        if ($availableAt?->isFuture()) {
            throw ValidationException::withMessages([
                'member_type' => "Your membership status was recently updated. You can change it again on {$availableAt->toFormattedDateString()}.",
            ]);
        }

        $user->setAttribute('membership_status_changed_at', now());
    }

    public function statusChangeAvailableAt(MobileUser $user): ?CarbonInterface
    {
        return $user->membership_status_changed_at?->copy()->addDays(self::STATUS_CHANGE_COOLDOWN_DAYS);
    }

    public function birthdayAttributes(array $attributes, ?MobileUser $current = null): array
    {
        $hasBirthday = array_key_exists('birthday', $attributes);
        $hasMonth = array_key_exists('birthday_month', $attributes);
        $hasDay = array_key_exists('birthday_day', $attributes);

        if (! $hasBirthday && ! $hasMonth && ! $hasDay) {
            return [
                'birthday_month' => $current?->birthday_month,
                'birthday_day' => $current?->birthday_day,
            ];
        }

        if ($hasBirthday) {
            $birthday = trim((string) ($attributes['birthday'] ?? ''));
            if ($birthday === '') {
                return ['birthday_month' => null, 'birthday_day' => null];
            }

            if (! preg_match('/^(\d{2})-(\d{2})$/', $birthday, $matches)) {
                throw ValidationException::withMessages([
                    'birthday' => 'Enter your birthday as month and day only, for example 07-21.',
                ]);
            }

            $month = (int) $matches[1];
            $day = (int) $matches[2];
        } else {
            $month = filled($attributes['birthday_month'] ?? null) ? (int) $attributes['birthday_month'] : null;
            $day = filled($attributes['birthday_day'] ?? null) ? (int) $attributes['birthday_day'] : null;

            if ($month === null && $day === null) {
                return ['birthday_month' => null, 'birthday_day' => null];
            }

            if ($month === null || $day === null) {
                throw ValidationException::withMessages([
                    'birthday' => 'Choose both the month and day for your birthday.',
                ]);
            }
        }

        if (! checkdate($month, $day, 2000)) {
            throw ValidationException::withMessages([
                'birthday' => 'Choose a valid birthday month and day.',
            ]);
        }

        return ['birthday_month' => $month, 'birthday_day' => $day];
    }

    public function birthday(MobileUser $user): ?string
    {
        if (! $user->birthday_month || ! $user->birthday_day) {
            return null;
        }

        return sprintf('%02d-%02d', $user->birthday_month, $user->birthday_day);
    }

    public function payload(MobileUser $user): array
    {
        $availableAt = $this->statusChangeAvailableAt($user);
        $isLocked = $availableAt?->isFuture() ?? false;
        $isVisitor = $this->isVisitor($user);

        return [
            'birthday' => $this->birthday($user),
            'birthday_month' => $user->birthday_month,
            'birthday_day' => $user->birthday_day,
            'membership_status_change_locked' => $isLocked,
            'membership_status_change_available_at' => $availableAt?->toIso8601String(),
            'membership_status_change_message' => $isLocked
                ? "Your membership status can be updated again on {$availableAt->toFormattedDateString()}."
                : 'You can update your membership status from your profile.',
            'triumphant_id_status_message' => $isVisitor
                ? 'You selected Visitor when you registered, so a Triumphant ID has not been assigned. Update your membership status to Church member when you are ready to receive one.'
                : (filled($user->triumphant_id)
                    ? 'Your Triumphant ID is active.'
                    : 'Your Triumphant ID will be assigned after your Church member status is confirmed.'),
        ];
    }
}
