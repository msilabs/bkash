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

        if ($enabled !== null) {
            return (bool) $enabled;
        }

        // Not set explicitly: enable only when at least one account is in sandbox mode
        foreach ((array) config('bkash.accounts', []) as $account) {
            if (!empty($account['sandbox'])) {
                return true;
            }
        }

        return false;
    }
}
