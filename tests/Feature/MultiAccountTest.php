<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Msilabs\Bkash\Facades\Bkash;
use Msilabs\Bkash\Tests\TestCase;

class MultiAccountTest extends TestCase
{
    public function test_named_account_uses_its_own_credentials_and_url(): void
    {
        $this->fakeBkash(['payment/execute' => ['transactionStatus' => 'Completed']]);

        Bkash::account('secondary')->executePayment('P1');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://tokenized.pay.bka.sh/v2/tokenized-checkout/auth/grant-token'
            && $request->hasHeader('username', 'secondary-user')
            && $request['app_key'] === 'secondary-key');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://tokenized.pay.bka.sh/v2/tokenized-checkout/payment/execute'
            && $request->hasHeader('X-App-Key', 'secondary-key'));
    }

    public function test_accounts_have_separate_token_cache(): void
    {
        $this->fakeBkash();

        Bkash::account('primary')->token();
        Bkash::account('secondary')->token();
        Bkash::account('primary')->token();

        Http::assertSentCount(2);
    }

    public function test_default_account_from_config(): void
    {
        config(['bkash.default' => 'secondary']);

        $this->assertSame('secondary', Bkash::account()->name());
        $this->assertFalse(Bkash::isSandbox());
    }

    public function test_account_instance_is_reused(): void
    {
        $this->assertSame(Bkash::account('primary'), Bkash::account('primary'));
    }

    public function test_unknown_account_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bKash account [missing] is not configured.');

        Bkash::account('missing');
    }

    public function test_using_runtime_credentials(): void
    {
        $this->fakeBkash(['payment/execute' => ['transactionStatus' => 'Completed']]);

        $client = Bkash::using([
            'sandbox' => 'true',
            'app_key' => 'db-key',
            'app_secret' => 'db-secret',
            'username' => 'db-user',
            'password' => 'db-pass',
        ], 'merchant-42');

        $this->assertSame('merchant-42', $client->name());
        $this->assertTrue($client->executePayment('P1')->isCompleted());

        Http::assertSent(fn (Request $request) => $request->url() === self::SANDBOX.'/payment/execute'
            && $request->hasHeader('X-App-Key', 'db-key'));
    }

    public function test_account_base_url_override(): void
    {
        config(['bkash.accounts.primary.base_url' => 'https://custom.example/v2/']);
        Bkash::purge();

        $this->assertSame('https://custom.example/v2', Bkash::baseUrl());
    }
}
