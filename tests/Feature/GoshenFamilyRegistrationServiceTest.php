<?php

namespace Tests\Feature;

use App\Models\MobileUser;
use App\Services\GoshenFamilyRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GoshenFamilyRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_family_children_use_manual_age_boundaries_and_required_gender(): void
    {
        $registrant = $this->registrant();
        $registration = app(GoshenFamilyRegistrationService::class)->prepare([
            'name' => 'Adeola Family',
            'father' => ['included' => true, 'first_name' => 'David', 'last_name' => 'Adeola', 'email' => $registrant->email, 'phone' => $registrant->phone],
            'mother' => ['included' => false],
            'children' => [
                ['first_name' => 'One', 'age' => 1, 'gender' => 'female'],
                ['first_name' => 'Fourteen', 'age' => 14, 'gender' => 'male'],
                ['first_name' => 'Fifteen', 'age' => 15, 'gender' => 'female'],
                ['first_name' => 'Seventeen', 'age' => 17, 'gender' => 'male'],
                ['first_name' => 'Eighteen', 'age' => 18, 'gender' => 'female', 'email' => 'eighteen@example.test', 'phone' => '447700000018', 'adult_confirmation' => true],
                ['first_name' => 'OneTwenty', 'age' => 120, 'gender' => 'male', 'email' => 'one.twenty@example.test', 'phone' => '447700000120', 'adult_confirmation' => true],
            ],
        ], $registrant);

        $children = collect($registration['members'])->where('role', 'child')->values();

        $this->assertSame([1, 14, 15, 17, 18, 120], $children->pluck('age')->all());
        $this->assertSame([false, false, true, true, true, true], $children->pluck('is_payable')->all());
        $this->assertSame(['female', 'male', 'female', 'male', 'female', 'male'], $children->pluck('gender')->all());
        $this->assertSame([null, null, null, null, null, null], $children->pluck('date_of_birth')->all());
        $this->assertSame('female', MobileUser::query()->where('email', 'eighteen@example.test')->sole()->gender);
        $this->assertSame('male', MobileUser::query()->where('email', 'one.twenty@example.test')->sole()->gender);
    }

    public function test_new_family_children_reject_non_integer_out_of_range_and_missing_gender(): void
    {
        $registrant = $this->registrant();
        $service = app(GoshenFamilyRegistrationService::class);

        foreach ([
            ['age' => 0, 'gender' => 'female', 'field' => 'family.children.0.age'],
            ['age' => 121, 'gender' => 'female', 'field' => 'family.children.0.age'],
            ['age' => '15.5', 'gender' => 'female', 'field' => 'family.children.0.age'],
            ['age' => 10, 'gender' => '', 'field' => 'family.children.0.gender'],
        ] as $invalid) {
            try {
                $service->prepare($this->family($registrant, [[
                    'first_name' => 'Invalid',
                    'last_name' => 'Child',
                    ...$invalid,
                ]]), $registrant);
                $this->fail('Expected child validation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($invalid['field'], $exception->errors());
            }
        }
    }

    public function test_adult_child_gender_must_match_an_existing_profile(): void
    {
        $registrant = $this->registrant();
        MobileUser::query()->create([
            'name' => 'Adult Child',
            'first_name' => 'Adult',
            'last_name' => 'Child',
            'email' => 'adult.child@example.test',
            'phone' => '447700000019',
            'gender' => 'male',
            'member_type' => 'church_member',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
        ]);

        try {
            app(GoshenFamilyRegistrationService::class)->prepare($this->family($registrant, [[
                'first_name' => 'Adult',
                'last_name' => 'Child',
                'age' => 18,
                'gender' => 'female',
                'email' => 'adult.child@example.test',
                'phone' => '447700000019',
                'adult_confirmation' => true,
            ]]), $registrant);
            $this->fail('Expected profile gender validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('family.children', $exception->errors());
        }
    }

    private function registrant(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'David Adeola',
            'first_name' => 'David',
            'last_name' => 'Adeola',
            'email' => 'david@example.test',
            'phone' => '447700000001',
            'gender' => 'male',
            'member_type' => 'church_member',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
        ]);
    }

    /** @param array<int, array<string, mixed>> $children @return array<string, mixed> */
    private function family(MobileUser $registrant, array $children): array
    {
        return [
            'name' => 'Adeola Family',
            'father' => ['included' => true, 'first_name' => 'David', 'last_name' => 'Adeola', 'email' => $registrant->email, 'phone' => $registrant->phone],
            'mother' => ['included' => false],
            'children' => $children,
        ];
    }
}
