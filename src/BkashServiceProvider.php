<?php

namespace Msilabs\Bkash;

use Illuminate\Support\ServiceProvider;

class BkashServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->sandboxRoutesEnabled()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->publishes([
            __DIR__.'/../config/bkash.php' => config_path('bkash.php'),
        ]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . "/../config/bkash.php", "bkash");
    }

    protected function sandboxRoutesEnabled(): bool
    {
        $enabled = config('bkash.sandbox_routes');

        // Not set explicitly: enable only in sandbox mode
        if ($enabled === null) {
            return (bool) config('bkash.sandbox');
        }

        return (bool) $enabled;
    }
}
