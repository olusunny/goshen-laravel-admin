<?php

namespace App\Providers;

use App\Services\Addons\AddonRuntimeLoader;
use Illuminate\Support\ServiceProvider;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('addons.php'), 'addons');

        $this->app->singleton(AddonRuntimeLoader::class);
        $this->app->make(AddonRuntimeLoader::class)->registerActiveAddons();
    }
}
