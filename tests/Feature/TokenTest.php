<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Exceptions\BkashException;
use Msilabs\Bkash\Facades\Bkash;
use Msilabs\Bkash\Tests\TestCase;

class TokenTest extends TestCase
{
    public function test_it_grants_token_with_credentials(): void
    {
        $this->fakeBkash();

        $this->assertSame('test-token', Bkash::token());

        Http::assertSent(function (Request $request) {
            return $request->url() === self::SANDBOX.'/auth/grant-token'
                && $request->hasHeader('username', 'primary-user')
                && $request->hasHeader('password', 'primary-pass')
                && $request['app_key'] === 'primary-key'
                && $request['app_secret'] === 'primary-secret';
        });
    }

    public function test_it_caches_the_token(): void
    {
        $this->fakeBkash();

        Bkash::token();
        Bkash::token();

        Http::assertSentCount(1);
    }

    public function test_fresh_token_skips_cache(): void
    {
        $this->fakeBkash();

        Bkash::token();
        Bkash::token(fresh: true);

        Http::assertSentCount(2);
    }

    public function test_it_uses_refresh_token_when_id_token_expired(): void
    {
        Http::fake([
            '*/auth/grant-token' => Http::response($this->tokenResponse('first-token', 'first-refresh')),
            '*/auth/refresh-token' => Http::response($this->tokenResponse('refreshed-token', 'second-refresh')),
        ]);

        Bkash::token();
        $this->forgetIdToken();

        $this->assertSame('refreshed-token', Bkash::token());

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth/refresh-token')
            && $request['refresh_token'] === 'first-refresh');
    }

    public function test_it_falls_back_to_grant_when_refresh_fails(): void
    {
        Http::fakeSequence('*/auth/grant-token')
            ->push($this->tokenResponse('first-token'))
            ->push($this->tokenResponse('second-token'));

        Http::fake([
            '*/auth/refresh-token' => Http::response(['statusCode' => '2079', 'statusMessage' => 'Invalid refresh token']),
        ]);

        Bkash::token();
        $this->forgetIdToken();

        $this->assertSame('second-token', Bkash::token());
    }

    public function test_it_throws_when_token_request_fails(): void
    {
        Http::fake([
            '*/auth/grant-token' => Http::response(['statusCode' => '2001', 'statusMessage' => 'Invalid App Key']),
        ]);

        try {
            Bkash::token();
            $this->fail('Expected BkashException.');
        } catch (BkashException $e) {
            $this->assertSame('2001', $e->errorCode);
            $this->assertStringContainsString('Invalid App Key', $e->getMessage());
        }
    }

    public function test_it_throws_when_credentials_missing(): void
    {
        config(['bkash.accounts.primary.app_key' => null]);

        $this->expectException(BkashException::class);
        $this->expectExceptionMessage('bKash account [primary] is missing [app_key].');

        Bkash::token();
    }

    public function test_forget_token_clears_cache(): void
    {
        $this->fakeBkash();

        Bkash::token();
        Bkash::forgetToken();
        Bkash::token();

        Http::assertSentCount(2);
    }

    protected function forgetIdToken(): void
    {
        $client = Bkash::account();

        $cacheKey = (new \ReflectionMethod($client, 'cacheKey'))->invoke($client, 'id_token');

        Cache::forget($cacheKey);
    }
}
