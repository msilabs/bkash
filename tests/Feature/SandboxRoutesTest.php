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
        $this->get('/bkash-sandbox/create-payment')
            ->assertOk()
            ->assertJson(['paymentID' => 'PAY1']);
    }

    public function test_execute_payment_route_redirects_to_bkash_without_payment_id()
    {
        $this->get('/bkash-sandbox/execute-payment')
            ->assertRedirect('https://bkash.test/pay');
    }

    public function test_execute_payment_route_executes_the_payment()
    {
        $this->get('/bkash-sandbox/execute-payment?paymentID=PAY1')
            ->assertOk()
            ->assertJson(['transactionStatus' => 'Completed']);
    }
}
