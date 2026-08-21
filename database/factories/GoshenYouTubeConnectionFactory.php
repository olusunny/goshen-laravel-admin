<?php

namespace Database\Factories;

use App\Models\GoshenYouTubeConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoshenYouTubeConnection>
 */
class GoshenYouTubeConnectionFactory extends Factory
{
    protected $model = GoshenYouTubeConnection::class;

    public function definition(): array
    {
        return [
            'channel_id' => 'UC'.$this->faker->unique()->bothify('??????????????????????'),
            'channel_title' => 'MFM Triumphant Church',
            'scopes' => config('goshen_experience.youtube.scopes'),
            'default_privacy' => 'private',
            'health' => GoshenYouTubeConnection::HEALTH_HEALTHY,
            'refresh_token_payload' => [
                'refresh_token' => 'test-refresh-token',
                'access_token' => 'test-access-token',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
            'connected_at' => now(),
        ];
    }
}
