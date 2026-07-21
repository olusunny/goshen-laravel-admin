<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoshenRetreatMemberProfilePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_dashboard_returns_non_birthday_profile_fields_needed_to_prefill_an_attendee(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        $member = MobileUser::query()->create([
            'name' => 'Grace Mercy Member',
            'first_name' => 'Grace',
            'last_name' => 'Member',
            'email' => 'grace.member@example.test',
            'phone' => '+447700900123',
            'password' => 'secret',
            'title' => 'Mrs.',
            'gender' => 'female',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'country_of_residence' => 'United Kingdom',
            'state_county_province' => 'London',
            'address' => '1 Goshen Way, London',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/goshen-retreat/me', [
            'data' => ['api_token' => $member->issueApiToken()],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.user.first_name', 'Grace')
            ->assertJsonPath('data.user.last_name', 'Member')
            ->assertJsonPath('data.user.phone', '+447700900123')
            ->assertJsonPath('data.user.gender', 'female')
            ->assertJsonPath('data.user.title', 'Mrs.')
            ->assertJsonPath('data.user.marital_status', 'Married')
            ->assertJsonPath('data.user.member_type', 'church_member')
            ->assertJsonPath('data.user.country_of_residence', 'United Kingdom')
            ->assertJsonPath('data.user.state_county_province', 'London')
            ->assertJsonPath('data.user.address', '1 Goshen Way, London');
    }
}
