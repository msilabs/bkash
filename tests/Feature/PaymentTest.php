<?php

namespace Msilabs\Bkash\Tests\Feature;

use ErrorException;
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
                && $request->header('X-App-Key') === ['primary-app-key']
                && $request['amount'] === 100
                && $request['merchantInvoiceNumber'] === 'INV-1'
                && $request['callbackURL'] === 'https://shop.test/callback'
                && $request['intent'] === 'sale';
        });
    }

    public function test_live_account_uses_the_live_base_url()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*' => Http::response(['paymentID' => 'PAY1', 'bkashURL' => 'https://bkash.test/pay']),
        ]);

        (new PaymentController)->useAccount('live')->createPayment(100, 'INV-1', 'https://shop.test/callback');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/create'
                && $request->header('X-App-Key') === ['live-app-key'];
        });
    }

    public function test_create_payment_error_includes_the_bkash_message()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*' => Http::response(['statusCode' => '2001', 'statusMessage' => 'Invalid App Key']),
        ]);

        try {
            (new PaymentController)->createPayment(100, 'INV-1', 'https://shop.test/callback');
            $this->fail('Expected an ErrorException.');
        } catch (ErrorException $e) {
            $this->assertStringContainsString('Error creating payment', $e->getMessage());
            $this->assertStringContainsString('Invalid App Key (2001)', $e->getMessage());
            $this->assertInstanceOf(ErrorException::class, $e->getPrevious());
        }
    }

    public function test_http_failure_throws_error_exception()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('status code: 500');

        (new PaymentController)->executePayment('PAY1');
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

    public function test_query_payment_calls_the_payment_status_api()
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token']),
            '*/payment/status' => Http::response(['transactionStatus' => 'Completed', 'trxID' => 'TRX1']),
        ]);

        $response = (new PaymentController)->queryPayment('PAY1');

        $this->assertSame('TRX1', $response->trxID);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/payment/status'
                && $request['paymentID'] === 'PAY1';
        });
    }
}
