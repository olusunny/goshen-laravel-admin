<?php

namespace Tests\Feature;

use App\Http\Controllers\GoshenYouTubeOAuthController;
use Illuminate\Http\Request;
use Tests\TestCase;

class GoshenYouTubeOAuthRouteTest extends TestCase
{
    public function test_nested_oauth_connect_path_resolves_to_the_oauth_controller_not_a_filament_record(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/admin/goshen-youtube/oauth/connect', 'GET'));

        $this->assertSame('admin.goshen-youtube.connect', $route->getName());
        $this->assertSame(GoshenYouTubeOAuthController::class.'@redirect', $route->getActionName());
        $this->assertSame(url('/admin/goshen-youtube/oauth/connect'), route('admin.goshen-youtube.connect'));
    }

    public function test_youtube_oauth_callback_uses_the_collision_free_registered_redirect_uri(): void
    {
        $this->assertSame(
            rtrim((string) config('app.url'), '/').'/admin/goshen-youtube/oauth/callback',
            config('goshen_experience.youtube.redirect_uri'),
        );
    }
}
