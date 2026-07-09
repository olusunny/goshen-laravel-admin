<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Models\Event;
use Tests\TestCase;

class GoshenRetreatEventMediaPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_payload_includes_feature_image_url_inquiry_phone_and_normalized_youtube_videos(): void
    {
        $this->enableGoshenRetreat();

        Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [
                'module' => 'goshen_retreat',
                'feature_banner' => [
                    'image_path' => 'goshen/retreat/banners/goshen-2026.jpg',
                ],
                'inquiry_phone' => ' +234 (801) 234-5678 ',
                'past_videos' => [
                    [
                        'title' => 'Opening Night',
                        'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'description' => 'Worship and teaching from the opening night.',
                        'sort_order' => 2,
                    ],
                    [
                        'title' => 'Morning Session',
                        'youtube_url' => 'https://youtu.be/9bZkp7q19f0',
                        'description' => '',
                        'sort_order' => 1,
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/goshen-retreat/events')
            ->assertOk()
            ->assertJsonPath('data.0.inquiry_phone', '+2348012345678')
            ->assertJsonPath('data.0.feature_image_path', 'goshen/retreat/banners/goshen-2026.jpg')
            ->assertJsonPath('data.0.past_videos.0.youtube_video_id', '9bZkp7q19f0')
            ->assertJsonPath('data.0.past_videos.0.youtube_url', 'https://www.youtube.com/watch?v=9bZkp7q19f0')
            ->assertJsonPath('data.0.past_videos.0.thumbnail_url', 'https://img.youtube.com/vi/9bZkp7q19f0/hqdefault.jpg')
            ->assertJsonPath('data.0.past_videos.0.title', 'Morning Session')
            ->assertJsonPath('data.0.past_videos.0.description', null)
            ->assertJsonPath('data.0.past_videos.1.youtube_video_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.0.past_videos.1.title', 'Opening Night')
            ->assertJsonPath('data.0.past_videos.1.description', 'Worship and teaching from the opening night.');

        $this->assertStringContainsString(
            '/storage/goshen/retreat/banners/goshen-2026.jpg',
            (string) $response->json('data.0.feature_image_url'),
        );
    }

    public function test_event_payload_does_not_expose_non_youtube_past_videos(): void
    {
        $this->enableGoshenRetreat();

        Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [
                'module' => 'goshen_retreat',
                'past_videos' => [
                    [
                        'title' => 'External Upload',
                        'youtube_url' => 'https://vimeo.com/123456789',
                        'description' => 'This should not be exposed.',
                    ],
                    [
                        'title' => 'Raw YouTube ID',
                        'youtube_url' => 'dQw4w9WgXcQ',
                    ],
                    [
                        'title' => 'Plain Website',
                        'youtube_url' => 'https://example.com/video.mp4',
                    ],
                ],
            ],
        ]);

        $this->getJson('/api/goshen-retreat/events')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.past_videos')
            ->assertJsonPath('data.0.past_videos.0.title', 'Raw YouTube ID')
            ->assertJsonPath('data.0.past_videos.0.youtube_video_id', 'dQw4w9WgXcQ');
    }

    private function enableGoshenRetreat(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );
    }
}
