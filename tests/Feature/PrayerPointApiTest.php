<?php

namespace Tests\Feature;

use App\Models\MobileUser;
use App\Models\PrayerPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrayerPointApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_prayer_points_only_return_published_records(): void
    {
        PrayerPoint::query()->create([
            'title' => 'Published point',
            'author' => 'Pastor',
            'content' => 'Pray for revival.',
            'date' => now()->toDateString(),
            'is_published' => true,
        ]);
        PrayerPoint::query()->create([
            'title' => 'Draft point',
            'author' => 'Pastor',
            'content' => 'Draft content.',
            'date' => now()->toDateString(),
            'is_published' => false,
        ]);

        $this->getJson('/api/prayer-points')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonFragment(['title' => 'Published point'])
            ->assertJsonMissing(['title' => 'Draft point']);
    }

    public function test_only_authorized_mobile_manager_can_manage_prayer_points(): void
    {
        $member = $this->member('member@example.test');
        $memberToken = $member->issueApiToken();

        $this->postJson('/api/control-hub/prayer-points', [
            'data' => [
                'api_token' => $memberToken,
                'title' => 'Unauthorized',
                'content' => 'Should not save.',
            ],
        ])->assertForbidden();

        $manager = $this->member('manager@example.test');
        $manager->assignRole(Role::query()->create([
            'name' => 'content_manager',
            'guard_name' => 'mobile',
        ]));
        $managerToken = $manager->issueApiToken();

        $create = $this->postJson('/api/control-hub/prayer-points', [
            'data' => [
                'api_token' => $managerToken,
                'title' => 'Morning prayer',
                'author' => 'Prayer Team',
                'content' => 'Pray for mercy.',
                'date' => now()->toDateString(),
                'is_published' => false,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.title', 'Morning prayer');

        $pointId = (int) $create->json('data.id');

        $this->postJson("/api/control-hub/prayer-points/{$pointId}/status", [
            'data' => [
                'api_token' => $managerToken,
                'is_published' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->postJson("/api/control-hub/prayer-points/{$pointId}", [
            'data' => [
                'api_token' => $managerToken,
                'title' => 'Evening prayer',
                'author' => 'Prayer Team',
                'content' => 'Pray for grace.',
                'date' => now()->toDateString(),
                'is_published' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Evening prayer');

        $this->postJson("/api/control-hub/prayer-points/{$pointId}/delete", [
            'data' => ['api_token' => $managerToken],
        ])->assertOk();

        $this->assertDatabaseMissing('prayer_points', ['id' => $pointId]);
    }

    private function member(string $email): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Prayer Member',
            'email' => $email,
            'phone' => '+447700900123',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }
}
