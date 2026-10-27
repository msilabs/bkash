<?php

namespace Msilabs\Bkash\Tests;

use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\BkashServiceProvider;
use Msilabs\Bkash\Facades\Bkash;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const SANDBOX = 'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout';

    protected function getPackageProviders($app): array
    {
        return [BkashServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Bkash' => Bkash::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');

        $app['config']->set('bkash.accounts', [
            'primary' => [
                'sandbox' => true,
                'app_key' => 'primary-key',
                'app_secret' => 'primary-secret',
                'username' => 'primary-user',
                'password' => 'primary-pass',
            ],
            'secondary' => [
                'sandbox' => false,
                'app_key' => 'secondary-key',
                'app_secret' => 'secondary-secret',
                'username' => 'secondary-user',
                'password' => 'secondary-pass',
            ],
        ]);
    }

    protected function tokenResponse(string $token = 'test-token', string $refresh = 'refresh-token'): array
    {
        return [
            'statusCode' => '0000',
            'statusMessage' => 'Successful',
            'id_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $refresh,
        ];
    }

    /**
     * Fake the grant token endpoint plus any extra endpoints (relative to tokenized-checkout/).
     *
     * @param  array<string, mixed>  $endpoints
     */
    protected function fakeBkash(array $endpoints = []): void
    {
        $fakes = ['*/tokenized-checkout/auth/grant-token' => Http::response($this->tokenResponse())];

        foreach ($endpoints as $endpoint => $response) {
            $fakes["*/tokenized-checkout/{$endpoint}"] = is_array($response) ? Http::response($response) : $response;
        }

        Http::fake($fakes);
    }
}
