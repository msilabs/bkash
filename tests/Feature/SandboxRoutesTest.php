<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Tests\TestCase;

class SandboxRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*/checkout/create' => Http::response(['paymentID' => 'PAY1', 'bkashURL' => 'https://bkash.test/pay']),
            '*/checkout/execute' => Http::response(['transactionStatus' => 'Completed']),
        ]);
    }

    public function test_create_payment_route_returns_json()
    {
        $this->get('/bkash-sandbox-validation/create-payment')
            ->assertOk()
            ->assertJson(['paymentID' => 'PAY1']);

        Http::assertSent(function ($request) {
            return strpos($request->url(), '/checkout/create') !== false
                && $request['callbackURL'] === 'http://localhost/bkash-sandbox-validation/execute-payment/primary';
        });
    }

    public function test_create_payment_route_uses_the_given_account()
    {
        $this->get('/bkash-sandbox-validation/create-payment/secondary')->assertOk();

        Http::assertSent(function ($request) {
            return strpos($request->url(), '/checkout/create') !== false
                && $request->header('X-App-Key') === ['secondary-app-key'];
        });
    }

    public function test_execute_payment_route_redirects_to_bkash_without_payment_id()
    {
        $this->get('/bkash-sandbox-validation/execute-payment')
            ->assertRedirect('https://bkash.test/pay');
    }

    public function test_execute_payment_route_executes_the_payment()
    {
        $this->get('/bkash-sandbox-validation/execute-payment?paymentID=PAY1')
            ->assertOk()
            ->assertJson(['transactionStatus' => 'Completed']);
    }

    public function test_live_and_unknown_accounts_are_rejected()
    {
        $this->get('/bkash-sandbox-validation/create-payment/live')->assertNotFound();
        $this->get('/bkash-sandbox-validation/execute-payment/live?paymentID=PAY1')->assertNotFound();
        $this->get('/bkash-sandbox-validation/create-payment/unknown')->assertNotFound();
    }
}
