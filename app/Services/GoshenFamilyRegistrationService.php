<?php

namespace App\Services;

use App\Models\MobileUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class GoshenFamilyRegistrationService
{
    public function isFamilyTicket(string $ticketName): bool
    {
        return str_contains(strtolower($ticketName), 'family');
    }

    /**
     * @return array{family_name: string, members: array<int, array<string, mixed>>, payable_count: int, complimentary_count: int, parent_names: array<string, string>}
     */
    public function prepare(array $family, MobileUser $registrant, bool $isManagerAssisted = false): array
    {
        $name = trim((string) ($family['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['family.name' => 'Enter a family name of up to 120 characters.']);
        }

        $parents = collect(['father', 'mother'])
            ->mapWithKeys(fn (string $role): array => [$role => $this->parent($family[$role] ?? null, $role, $registrant)])
            ->filter()
            ->all();

        if ($parents === []) {
            throw ValidationException::withMessages(['family' => 'Add at least one parent to the family registration.']);
        }

        if (! $isManagerAssisted && ! collect($parents)->contains(fn (array $parent): bool => $this->matchesRegistrant($parent, $registrant))) {
            throw ValidationException::withMessages(['family' => 'Register your family using your own parent details.']);
        }

        $children = array_map(
            fn (mixed $child, int $index): array => $this->child(is_array($child) ? $child : [], $index),
            array_values(is_array($family['children'] ?? null) ? $family['children'] : []),
            array_keys(array_values(is_array($family['children'] ?? null) ? $family['children'] : [])),
        );

        $members = array_values($parents);
        foreach ($children as $child) {
            $members[] = $child;
        }

        $payableCount = count($parents) + collect($children)->where('is_payable', true)->count();

        return [
            'family_name' => $name,
            'members' => $members,
            'payable_count' => $payableCount,
            'complimentary_count' => collect($children)->where('is_payable', false)->count(),
            'parent_names' => collect($parents)->mapWithKeys(fn (array $parent, string $role): array => [$role => $parent['name']])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function parent(mixed $input, string $role, MobileUser $registrant): ?array
    {
        if (! is_array($input) || ! filter_var($input['included'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        if ($firstName === '') {
            throw ValidationException::withMessages(["family.{$role}.first_name" => "Enter the {$role}'s first name."]);
        }

        $member = $this->matchingMember($this->email($input['email'] ?? null), $this->phone($input['phone'] ?? null));
        if ($this->matchesRegistrant(['email' => $this->email($input['email'] ?? null), 'phone' => $this->phone($input['phone'] ?? null)], $registrant)) {
            $member = $registrant;
        }

        return [
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $this->email($input['email'] ?? null),
            'phone' => $this->phone($input['phone'] ?? null),
            'date_of_birth' => null,
            'age' => null,
            'is_payable' => true,
            'mobile_user_id' => $member?->id,
            'profile_status' => $member ? 'linked' : 'not_linked',
        ];
    }

    /** @return array<string, mixed> */
    private function child(array $input, int $index): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        if ($firstName === '') {
            throw ValidationException::withMessages(["family.children.{$index}.first_name" => 'Enter the child\'s first name.']);
        }

        $dateOfBirth = $this->dateOfBirth($input['date_of_birth'] ?? null, $index);
        $age = $dateOfBirth->age;
        $email = $this->email($input['email'] ?? null);
        $phone = $this->phone($input['phone'] ?? null);
        $member = null;
        $profileStatus = 'not_required';

        if ($age >= 18) {
            if ($email === null || $phone === null) {
                throw ValidationException::withMessages(["family.children.{$index}" => 'Children aged 18 or over need an email address and phone number.']);
            }
            if (! filter_var($input['adult_confirmation'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages(["family.children.{$index}.adult_confirmation" => 'Confirm that this child is 18 or over.']);
            }
            $member = $this->findOrCreateAdult($firstName, $lastName, $email, $phone);
            $profileStatus = $member->is_verified ? 'linked' : 'activation_pending';
        }

        return [
            'role' => 'child',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $dateOfBirth->toDateString(),
            'age' => $age,
            'is_payable' => $age >= 15,
            'mobile_user_id' => $member?->id,
            'profile_status' => $profileStatus,
        ];
    }

    private function findOrCreateAdult(string $firstName, string $lastName, string $email, string $phone): MobileUser
    {
        $matches = $this->matchingMembers($email, $phone);

        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['family.children' => 'The adult child contact details match more than one profile. Please contact the church office.']);
        }

        if ($matches->isNotEmpty()) {
            $member = $matches->first();
            if (strtolower((string) $member->email) !== strtolower($email) || $this->phone($member->phone) !== $phone) {
                throw ValidationException::withMessages(['family.children' => 'The adult child email and phone must match the same existing profile.']);
            }

            return $member;
        }

        $member = MobileUser::query()->create([
            'name' => trim("{$firstName} {$lastName}"),
            'first_name' => $firstName,
            'last_name' => $lastName ?: null,
            'email' => strtolower($email),
            'phone' => $phone,
            'member_type' => 'church_member',
            'login_type' => 'family_registration',
            'is_verified' => false,
            'is_blocked' => false,
            'is_deleted' => false,
            'adult_confirmed_at' => now(),
        ]);
        app(TriumphantIdService::class)->assignFor($member);

        $code = (string) random_int(100000, 999999);
        $member->forceFill([
            'password_reset_code_hash' => Hash::make($code),
            'password_reset_expires_at' => now()->addMinutes(30),
        ])->save();

        DB::afterCommit(function () use ($member, $code): void {
            Mail::raw(
                "A Goshen Retreat profile has been created for you. Use this code to set your password: {$code}",
                fn ($message) => $message->to($member->email)->subject('Complete your MFM Triumphant Church profile'),
            );
        });

        return $member;
    }

    private function matchesRegistrant(array $parent, MobileUser $registrant): bool
    {
        return ($parent['email'] !== null && strtolower($parent['email']) === strtolower((string) $registrant->email))
            || ($parent['phone'] !== null && $parent['phone'] === $this->phone($registrant->phone));
    }

    private function matchingMember(?string $email, ?string $phone): ?MobileUser
    {
        if ($email === null && $phone === null) {
            return null;
        }

        $matches = $this->matchingMembers($email, $phone);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, MobileUser> */
    private function matchingMembers(?string $email, ?string $phone)
    {
        return MobileUser::query()
            ->where('is_deleted', false)
            ->where(function ($query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->whereRaw('LOWER(email) = ?', [strtolower($email)]);
                }

                if ($phone !== null) {
                    $query->{$email === null ? 'whereRaw' : 'orWhereRaw'}("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$phone]);
                }
            })
            ->lockForUpdate()
            ->get();
    }

    private function dateOfBirth(mixed $value, int $index): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(["family.children.{$index}.date_of_birth" => 'Enter a valid child date of birth.']);
        }

        if ($date->isFuture() || $date->age < 1) {
            throw ValidationException::withMessages(["family.children.{$index}.date_of_birth" => 'The child must be at least one year old.']);
        }

        return $date;
    }

    private function email(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));

        if ($email === '') {
            return null;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['family' => 'Enter a valid email address.']);
        }

        return $email;
    }

    private function phone(mixed $value): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $value);

        return $phone !== '' ? $phone : null;
    }
}
