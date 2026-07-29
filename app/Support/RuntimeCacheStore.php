<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RuntimeCacheStore
{
    public const SETTING_KEY = 'redis_cache_enabled';

    public function __construct(private readonly Redis $redis) {}

    public function configure(): void
    {
        $store = $this->redisIsAvailable() ? 'redis' : 'database';

        config()->set('cache.default', $store);
        Cache::setDefaultDriver($store);
        Cache::forgetDriver($store);
    }

    private function redisIsAvailable(): bool
    {
        try {
            if (! filter_var(AppSetting::value(self::SETTING_KEY, '0'), FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }

            return (bool) $this->redis->connection(config('cache.stores.redis.connection', 'cache'))->ping();
        } catch (Throwable) {
            return false;
        }
    }
}
