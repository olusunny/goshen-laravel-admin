<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\RuntimeCacheStore;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RuntimeCacheStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'database');
    }

    public function test_it_uses_redis_only_when_the_admin_setting_is_enabled_and_redis_is_reachable(): void
    {
        AppSetting::query()->create([
            'group' => 'system',
            'key' => RuntimeCacheStore::SETTING_KEY,
            'value' => '1',
        ]);

        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->once()->andReturn('PONG');

        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('connection')->once()->with('cache')->andReturn($connection);
        $this->app->instance(Redis::class, $redis);

        app(RuntimeCacheStore::class)->configure();

        $this->assertSame('redis', config('cache.default'));
        $this->assertSame('redis', app('cache')->getDefaultDriver());
    }

    public function test_it_keeps_database_cache_when_redis_is_disabled(): void
    {
        $redis = Mockery::mock(Redis::class);
        $redis->shouldNotReceive('connection');
        $this->app->instance(Redis::class, $redis);

        app(RuntimeCacheStore::class)->configure();
        $this->assertSame('database', config('cache.default'));
        $this->assertSame('database', app('cache')->getDefaultDriver());
    }

    public function test_it_keeps_database_cache_when_redis_is_unreachable(): void
    {
        AppSetting::query()->create([
            'group' => 'system',
            'key' => RuntimeCacheStore::SETTING_KEY,
            'value' => '1',
        ]);

        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('connection')->once()->with('cache')->andThrow(new \RuntimeException('Redis unavailable'));
        $this->app->instance(Redis::class, $redis);

        app(RuntimeCacheStore::class)->configure();
        $this->assertSame('database', config('cache.default'));
        $this->assertSame('database', app('cache')->getDefaultDriver());
    }
}
