<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RuntimeCacheStore
{
    public const SETTING_KEY = 'redis_cache_enabled';

    public function configure(): void
    {
        $store = $this->isEnabled() ? 'redis_failover' : 'database';

        config()->set('cache.default', $store);
        Cache::setDefaultDriver($store);
        Cache::forgetDriver(['database', 'redis_failover']);
    }

    private function isEnabled(): bool
    {
        try {
            return filter_var(AppSetting::value(self::SETTING_KEY, '0'), FILTER_VALIDATE_BOOLEAN);
        } catch (Throwable) {
            return false;
        }
    }
}
