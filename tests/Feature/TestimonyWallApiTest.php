<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MobileUser;
use App\Models\Testimony;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestimonyWallApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_toggle_blocks_public_access_and_submission(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'testimonies_enabled'],
            ['group' => 'modules', 'value' => '0', 'is_secret' => false],
        );

        $user = MobileUser::create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->getJson('/testimonies')->assertForbidden()->assertJsonPath('status', 'disabled');
        $this->postJson('/testimonies', [
            'data' => [
                'api_token' => $user->issueApiToken(),
                'title' => 'God answered',
                'body' => 'I am thankful.',
            ],
        ])->assertForbidden()->assertJsonPath('status', 'disabled');
    }

    public function test_verified_user_submits_pending_testimony_and_public_list_shows_only_approved(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'testimonies_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );
        $user = MobileUser::create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
            'country_of_residence' => 'Nigeria',
        ]);

        $this->postJson('/testimonies', [
            'data' => [
                'api_token' => $user->issueApiToken(),
                'title' => 'God answered',
                'body' => 'I am thankful for God mercy.',
                'is_anonymous' => false,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('testimonies', [
            'mobile_user_id' => $user->id,
            'status' => Testimony::STATUS_PENDING,
        ]);

        $this->getJson('/testimonies')
            ->assertOk()
            ->assertJsonCount(0, 'testimonies');

        Testimony::first()->approve();

        $this->getJson('/testimonies')
            ->assertOk()
            ->assertJsonPath('testimonies.0.identity', 'Ada')
            ->assertJsonPath('testimonies.0.country_of_residence', 'Nigeria');
    }

    public function test_audio_testimony_requires_duration_under_one_hundred_twenty_seconds(): void
    {
        Storage::fake('public');
        AppSetting::query()->updateOrCreate(
            ['key' => 'testimonies_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );
        $user = MobileUser::create([
            'name' => 'Ben',
            'email' => 'ben@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->post('/testimonies', [
            'data' => json_encode([
                'api_token' => $user->issueApiToken(),
                'title' => 'Too long',
                'body' => 'Audio is too long.',
                'audio_duration_seconds' => 121,
            ]),
            'audio' => UploadedFile::fake()->create('testimony.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio_duration_seconds');
    }
}
