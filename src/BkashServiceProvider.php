<?php

namespace Msilabs\Bkash;

use Illuminate\Support\ServiceProvider;

class BkashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bkash.php', 'bkash');

        $this->app->singleton(BkashManager::class, fn ($app) => new BkashManager($app['config'], $app['cache']));

        $this->app->alias(BkashManager::class, 'bkash');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/bkash.php' => config_path('bkash.php'),
            ], 'bkash-config');
        }

        if ($this->app['config']->get('bkash.sandbox_routes')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
