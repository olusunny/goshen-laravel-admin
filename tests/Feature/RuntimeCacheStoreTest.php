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
use PDO;
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
        $database = tempnam(sys_get_temp_dir(), 'goshen-redis-cache-');
        $pdo = new PDO("sqlite:{$database}");
        $pdo->exec('create table app_settings (id integer primary key, "group" varchar, key varchar, value varchar, is_secret integer, description varchar, created_at datetime, updated_at datetime)');
        $pdo->exec("insert into app_settings (id, \"group\", key, value, is_secret) values (1, 'performance', 'redis_cache_enabled', '1', 0)");

        $environment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'CACHE_STORE' => 'database',
        ];

        try {
            $configCache = new Process([PHP_BINARY, 'artisan', 'config:cache'], base_path(), $environment);
            $configCache->run();
            $this->assertTrue($configCache->isSuccessful(), $configCache->getErrorOutput());

            $cachedConfig = base_path('bootstrap/cache/config.php');
            $definition = new Process([
                PHP_BINARY,
                '-r',
                'echo (require $argv[1])["cache"]["stores"]["redis_failover"]["driver"];',
                $cachedConfig,
            ]);
            $definition->run();

            $this->assertTrue($definition->isSuccessful(), $definition->getErrorOutput());
            $this->assertSame('failover', $definition->getOutput());

            $process = new Process([
                PHP_BINARY,
                'artisan',
                'tinker',
                '--execute=echo app("cache")->getDefaultDriver();',
            ], base_path(), $environment);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame('redis_failover', trim($process->getOutput()));
        } finally {
            Artisan::call('config:clear');
            @unlink($database);
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
