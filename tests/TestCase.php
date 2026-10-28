<?php

namespace Msilabs\Bkash\Tests;

use Msilabs\Bkash\BkashServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected $sandbox = true;

    protected function getPackageProviders($app)
    {
        return [BkashServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('bkash.sandbox', $this->sandbox);
        $app['config']->set('bkash.app_key', 'app-key');
        $app['config']->set('bkash.app_secret', 'app-secret');
        $app['config']->set('bkash.username', 'username');
        $app['config']->set('bkash.password', 'password');
        $app['config']->set('bkash.callback_url', '/bkash/callback');
    }

    protected function isTokenRequest($request)
    {
        return strpos($request->url(), '/tokenized/checkout/token/grant') !== false;
    }
}
