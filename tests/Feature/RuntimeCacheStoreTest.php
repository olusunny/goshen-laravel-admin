<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\RuntimeCacheStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RuntimeCacheStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'database');
    }

    public function test_it_uses_database_cache_when_redis_is_disabled(): void
    {
        $this->setRedisCacheEnabled(false);

        app(RuntimeCacheStore::class)->configure();

        $this->assertSame('database', config('cache.default'));
        $this->assertSame('database', app('cache')->getDefaultDriver());
    }

    public function test_it_uses_native_failover_when_redis_is_enabled_and_falls_back_for_cache_and_locks(): void
    {
        $this->setRedisCacheEnabled(true);
        Cache::extend('broken_redis', function ($app, array $config): Repository {
            return $this->repository(new class extends ArrayStore implements LockProvider {
                public function get($key)
                {
                    throw new \RuntimeException('Redis unavailable');
                }

                public function put($key, $value, $seconds)
                {
                    throw new \RuntimeException('Redis unavailable');
                }

                public function lock($name, $seconds = 0, $owner = null)
                {
                    throw new \RuntimeException('Redis unavailable');
                }

                public function restoreLock($name, $owner)
                {
                    throw new \RuntimeException('Redis unavailable');
                }
            }, $config);
        });
        config()->set('cache.stores.broken_redis', ['driver' => 'broken_redis']);
        config()->set('cache.stores.redis_failover.stores', ['broken_redis', 'database']);

        app(RuntimeCacheStore::class)->configure();

        $this->assertSame('redis_failover', config('cache.default'));
        $this->assertTrue(Cache::put('redis-outage', 'database-fallback', 60));
        $this->assertSame('database-fallback', Cache::get('redis-outage'));

        $lock = Cache::lock('redis-outage-lock', 60);
        $this->assertTrue($lock->get());
        $this->assertTrue($lock->release());
    }

    public function test_config_cache_keeps_the_failover_definition_for_a_fresh_php_process(): void
    {
        Artisan::call('config:cache');

        try {
            $cachedConfig = base_path('bootstrap/cache/config.php');
            $process = new Process([
                PHP_BINARY,
                '-r',
                'echo (require $argv[1])["cache"]["stores"]["redis_failover"]["driver"];',
                $cachedConfig,
            ]);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame('failover', $process->getOutput());
        } finally {
            Artisan::call('config:clear');
        }
    }

    private function setRedisCacheEnabled(bool $enabled): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => RuntimeCacheStore::SETTING_KEY],
            ['group' => 'performance', 'value' => $enabled ? '1' : '0'],
        );
    }
}
