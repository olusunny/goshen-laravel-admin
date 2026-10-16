<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Hymn;
use App\Services\Hymns\HymnAddon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HymnAddonAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_public_and_survives_an_absent_package(): void
    {
        $this->getJson('/api/v1/hymns/status')->assertOk()
            ->assertJsonPath('schema_version', 1)
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('reason', 'not_installed')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_disabled_legacy_aliases_never_leak_published_rows(): void
    {
        Hymn::query()->create(['title' => 'Existing hymn', 'number' => '1', 'content' => '<p>Old lyrics</p>', 'is_published' => true]);
        foreach (['/api/fetch_hymns', '/fetch_hymns'] as $url) {
            $this->postJson($url, ['data' => ['page' => '0']])->assertOk()
                ->assertJsonPath('status', 'ok')->assertJsonPath('hymns', [])
                ->assertJsonPath('isLastPage', true);
        }
        $this->assertDatabaseCount('hymns', 1);
    }

    public function test_inactive_and_uninstalled_packages_cannot_read_direct_urls(): void
    {
        $addon = Addon::query()->create(['package_key' => 'church-tools.digital-hymn-book', 'name' => 'Digital Hymn Book',
            'installed_version' => '1.0.0', 'status' => Addon::STATUS_INACTIVE, 'manifest' => []]);
        foreach ([Addon::STATUS_INACTIVE, Addon::STATUS_UNINSTALLED] as $status) {
            $addon->update(['status' => $status]);
            foreach (['/api/v1/hymns/books', '/api/v1/hymns', '/api/v1/hymns/1',
                '/api/v1/hymns/books/1/manifest', '/api/v1/hymns/books/1/download/old-revision'] as $url) {
                $this->getJson($url)->assertStatus(409)->assertJsonPath('code', 'hymn_disabled');
            }
        }
    }

    public function test_global_disable_is_reported_without_loading_package_code(): void
    {
        config(['addons.enabled' => false]);
        $this->getJson('/api/v1/hymns/status')->assertOk()->assertJsonPath('enabled', false)
            ->assertJsonPath('reason', 'disabled');
    }

    public function test_legacy_response_remains_empty_when_deactivation_races_discovery(): void
    {
        $addon = \Mockery::mock(HymnAddon::class);
        $addon->shouldReceive('status')->once()->andReturn(['enabled' => true]);
        $addon->shouldReceive('catalog')->once()->andReturn(null);
        $this->app->instance(HymnAddon::class, $addon);
        $this->postJson('/api/fetch_hymns', ['data' => []])->assertOk()->assertJsonPath('hymns', [])->assertJsonPath('isLastPage', true);
    }
}
