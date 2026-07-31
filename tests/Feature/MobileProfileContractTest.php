<?php

namespace Tests\Feature;

use App\Models\ChurchGroup;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileProfileContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_and_session_read_share_the_web_profile_contract(): void
    {
        $group = ChurchGroup::query()->create(['name' => 'Profile Contract Group', 'is_active' => true]);
        $user = MobileUser::query()->create([
            'name' => 'Profile Contract Member',
            'email' => 'profile-contract@example.test',
            'password' => Hash::make('Passw0rd!234'),
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $token = $user->issueApiToken();

        $payload = [
            'api_token' => $token,
            'fullname' => 'Grace Mercy Member',
            'title' => 'Mrs.',
            'first_name' => 'Grace',
            'middle_name' => 'Mercy',
            'last_name' => 'Member',
            'phone' => '+447700900123',
            'gender' => 'female',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'birthday_month_day' => '07-31',
            'adult_confirmation' => true,
            'group_id' => $group->id,
            'country_of_residence' => 'United Kingdom',
            'state_county_province' => 'London',
            'address' => '1 Goshen Way, London',
            'about_me' => 'Serving with joy.',
        ];

        $this->postJson('/updateProfile', $payload)
            ->assertOk()
            ->assertJsonPath('user.first_name', 'Grace')
            ->assertJsonPath('user.middle_name', 'Mercy')
            ->assertJsonPath('user.last_name', 'Member')
            ->assertJsonPath('user.title', 'Mrs.')
            ->assertJsonPath('user.marital_status', 'Married')
            ->assertJsonPath('user.member_type', 'church_member')
            ->assertJsonPath('user.birthday', '07-31')
            ->assertJsonPath('user.birthday_month_day', '07-31')
            ->assertJsonPath('user.adult_confirmation', true)
            ->assertJsonPath('user.group_id', $group->id)
            ->assertJsonPath('user.country_of_residence', 'United Kingdom')
            ->assertJsonPath('user.state_county_province', 'London')
            ->assertJsonPath('user.address', '1 Goshen Way, London')
            ->assertJsonPath('user.about_me', 'Serving with joy.');

        $this->postJson('/member/me', ['data' => ['api_token' => $token]])
            ->assertOk()
            ->assertJsonPath('user.birthday_month_day', '07-31')
            ->assertJsonPath('user.adult_confirmation', true)
            ->assertJsonPath('user.group_name', 'Profile Contract Group')
            ->assertJsonPath('user.about_me', 'Serving with joy.');

        $this->assertDatabaseHas('mobile_users', [
            'id' => $user->id,
            'name' => 'Grace Mercy Member',
            'title' => 'Mrs.',
            'first_name' => 'Grace',
            'middle_name' => 'Mercy',
            'last_name' => 'Member',
            'phone' => '+447700900123',
            'gender' => 'female',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'group_id' => $group->id,
            'country_of_residence' => 'United Kingdom',
            'state_county_province' => 'London',
            'address' => '1 Goshen Way, London',
            'birthday_month' => 7,
            'birthday_day' => 31,
            'bio' => 'Serving with joy.',
        ]);
        $this->assertNotNull($user->fresh()->adult_confirmed_at);
    }
}
