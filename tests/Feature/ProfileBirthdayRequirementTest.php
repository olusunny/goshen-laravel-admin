<?php

namespace Tests\Feature;

use App\Models\ChurchGroup;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileBirthdayRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_church_member_profile_update_requires_a_birthday(): void
    {
        $group = ChurchGroup::query()->create([
            'name' => 'Birthday Requirement Group',
            'is_active' => true,
        ]);
        $member = MobileUser::query()->create([
            'name' => 'Birthday Requirement Member',
            'email' => 'birthday-required@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/updateProfile', [
            'api_token' => $member->issueApiToken(),
            'first_name' => 'Birthday',
            'last_name' => 'Member',
            'phone' => '+447700900456',
            'gender' => 'female',
            'member_type' => 'church_member',
            'title' => 'Mrs.',
            'marital_status' => 'Married',
            'group_id' => $group->id,
            'country_of_residence' => 'United Kingdom',
            'state_county_province' => 'London',
            'address' => '2 Goshen Way, London',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('birthday');
    }
}
