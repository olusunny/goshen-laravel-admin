<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Services;

use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayPreference;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BirthdayEligibilityService
{
    public const ELIGIBLE = 'ELIGIBLE';
    public const INELIGIBLE = 'MEMBER_INELIGIBLE';
    public const OPTED_OUT = 'BIRTHDAY_OPTED_OUT';

    public function eligible(Model $member, ?BirthdayPreference $preference = null): bool
    {
        return $this->reason($member, $preference) === self::ELIGIBLE;
    }

    public function baseEligible(Model $member): bool
    {
        return str((string) $member->member_type)->trim()->lower()->toString() === 'church_member'
            && (bool) $member->is_verified
            && ! (bool) $member->is_blocked
            && ! (bool) $member->is_deleted
            && filled($member->triumphant_id);
    }

    public function reason(Model $member, ?BirthdayPreference $preference = null): string
    {
        if (! $this->baseEligible($member) || ! checkdate((int) $member->birthday_month, (int) $member->birthday_day, 2000)) {
            return self::INELIGIBLE;
        }

        return ($preference?->visibility_enabled ?? true) ? self::ELIGIBLE : self::OPTED_OUT;
    }

    public function occursOn(Model $member, CarbonImmutable $date): bool
    {
        $month = (int) $member->birthday_month;
        $day = (int) $member->birthday_day;

        if ($month !== 2 || $day !== 29 || $date->isLeapYear()) {
            return $month === $date->month && $day === $date->day;
        }

        $policy = BirthdaySetting::value('feb_29_policy', config('church-birthday-celebrations.feb_29_policy', 'february_28'));
        if (! in_array($policy, ['february_28', 'march_1'], true)) {
            $policy = 'february_28';
        }

        return $policy === 'march_1'
            ? $date->month === 3 && $date->day === 1
            : $date->month === 2 && $date->day === 28;
    }

    public function members(): Builder
    {
        $class = config('church-birthday-celebrations.models.mobile_user');

        return $class::query()
            ->where('member_type', 'church_member')
            ->where('is_verified', true)
            ->where('is_blocked', false)
            ->where('is_deleted', false)
            ->whereNotNull('triumphant_id')
            ->where('triumphant_id', '!=', '')
            ->orderBy('id');
    }

    public function membersFor(int $month, int $day): Builder
    {
        return $this->members()
            ->where('birthday_month', $month)
            ->where('birthday_day', $day);
    }
}
