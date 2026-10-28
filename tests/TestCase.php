<?php

namespace Msilabs\Bkash\Tests;

use Msilabs\Bkash\BkashServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [BkashServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('bkash.accounts', $this->accounts());
    }

    protected function accounts()
    {
        return [
            'primary' => $this->account('primary', true),
            'secondary' => $this->account('secondary', true),
            'live' => $this->account('live', false),
        ];
    }

    protected function account($name, $sandbox)
    {
        return [
            'sandbox' => $sandbox,
            'app_key' => "{$name}-app-key",
            'app_secret' => "{$name}-app-secret",
            'username' => "{$name}-username",
            'password' => "{$name}-password",
        ];
    }

    protected function isTokenRequest($request)
    {
        return strpos($request->url(), '/tokenized/checkout/token/grant') !== false;
    }
}
