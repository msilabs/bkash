<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Tests\Fixtures\PaymentController;
use Msilabs\Bkash\Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_create_payment_sends_the_expected_payload()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*/checkout/create' => Http::response(['paymentID' => 'PAY1', 'bkashURL' => 'https://bkash.test/pay']),
        ]);

        $response = (new PaymentController)->createPayment(100, 'INV-1', 'https://shop.test/callback');

        $this->assertSame('PAY1', $response->paymentID);
        $this->assertSame('https://bkash.test/pay', $response->bkashURL);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create'
                && $request->header('Authorization') === ['token']
                && $request->header('X-App-Key') === ['app-key']
                && $request['amount'] === 100
                && $request['merchantInvoiceNumber'] === 'INV-1'
                && $request['callbackURL'] === 'https://shop.test/callback'
                && $request['intent'] === 'sale';
        });
    }

    public function test_create_payment_uses_default_invoice_and_callback_url()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*' => Http::response(['paymentID' => 'PAY1']),
        ]);

        (new PaymentController)->createPayment(100);

        Http::assertSent(function ($request) {
            return strpos($request->url(), '/checkout/create') !== false
                && $request['merchantInvoiceNumber'] !== ''
                && $request['callbackURL'] === 'http://localhost/bkash/callback';
        });
    }

    public function test_live_mode_uses_the_live_base_url()
    {
        config(['bkash.sandbox' => false]);

        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*' => Http::response(['paymentID' => 'PAY1']),
        ]);

        (new PaymentController)->createPayment(100);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/create';
        });
    }

    public function test_execute_payment_returns_the_response_object()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*/checkout/execute' => Http::response(['transactionStatus' => 'Completed', 'trxID' => 'TRX1']),
        ]);

        $response = (new PaymentController)->executePayment('PAY1');

        $this->assertSame('Completed', $response->transactionStatus);
        $this->assertSame('TRX1', $response->trxID);

        Http::assertSent(function ($request) {
            return strpos($request->url(), '/checkout/execute') !== false
                && $request['paymentID'] === 'PAY1';
        });
    }

    public function test_bkash_errors_are_returned_as_object_not_thrown()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*' => Http::response(['statusCode' => '2001', 'statusMessage' => 'Invalid App Key']),
        ]);

        $response = (new PaymentController)->createPayment(100);

        $this->assertSame('2001', $response->statusCode);
        $this->assertSame('Invalid App Key', $response->statusMessage);
    }
}
