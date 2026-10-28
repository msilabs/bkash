<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Exceptions\BkashException;
use Msilabs\Bkash\Facades\Bkash;
use Msilabs\Bkash\Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_create_payment(): void
    {
        $this->fakeBkash([
            'payment/create' => [
                'paymentId' => 'TR0011abc',
                'bkashURL' => 'https://sandbox.payment.bkash.com/?paymentId=TR0011abc',
                'transactionStatus' => 'Initiated',
            ],
        ]);

        $response = Bkash::createPayment(100, 'INV-1', 'https://shop.test/callback');

        $this->assertTrue($response->successful());
        $this->assertSame('TR0011abc', $response->paymentId);
        $this->assertSame('https://sandbox.payment.bkash.com/?paymentId=TR0011abc', $response->redirectUrl());

        Http::assertSent(function (Request $request) {
            return $request->url() === self::SANDBOX.'/payment/create'
                && $request->hasHeader('Authorization', 'test-token')
                && $request->hasHeader('X-App-Key', 'primary-key')
                && $request->data() === [
                    'payerReference' => 'INV-1',
                    'callbackURL' => 'https://shop.test/callback',
                    'amount' => '100.00',
                    'currency' => 'BDT',
                    'intent' => 'sale',
                    'merchantInvoiceNumber' => 'INV-1',
                ];
        });
    }

    public function test_create_payment_with_payer_reference_and_extra(): void
    {
        $this->fakeBkash(['payment/create' => ['paymentId' => 'P1', 'bKashURL' => 'https://x']]);

        $response = Bkash::createPayment(55.5, 'INV-2', 'https://cb', '01700000000', ['subMerchantName' => 'Shop']);

        $this->assertSame('https://x', $response->redirectUrl());

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payment/create')
            && $request['payerReference'] === '01700000000'
            && $request['amount'] === '55.50'
            && $request['subMerchantName'] === 'Shop');
    }

    public function test_execute_payment(): void
    {
        $this->fakeBkash([
            'payment/execute' => [
                'paymentId' => 'P1',
                'trxId' => 'TRX1',
                'transactionStatus' => 'Completed',
                'merchantInvoiceNumber' => 'INV-1',
            ],
        ]);

        $response = Bkash::executePayment('P1');

        $this->assertTrue($response->isCompleted());
        $this->assertSame('TRX1', $response['trxId']);
        $this->assertSame('INV-1', $response->invoiceNumber());

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payment/execute')
            && $request->data() === ['paymentId' => 'P1']);
    }

    public function test_failed_execute_returns_error_response(): void
    {
        $this->fakeBkash([
            'payment/execute' => [
                'internalCode' => 'X',
                'externalCode' => '2023',
                'errorMessageEn' => 'Insufficient Balance',
                'errorMessageBn' => 'অপর্যাপ্ত ব্যালেন্স',
            ],
        ]);

        $response = Bkash::executePayment('P1');

        $this->assertTrue($response->failed());
        $this->assertFalse($response->isCompleted());
        $this->assertSame('2023', $response->errorCode());
        $this->assertSame('Insufficient Balance', $response->errorMessage());
        $this->assertSame('অপর্যাপ্ত ব্যালেন্স', $response->errorMessageBn());

        $this->expectException(BkashException::class);
        $this->expectExceptionMessage('[2023] Insufficient Balance');

        $response->throw();
    }

    public function test_integer_zero_status_code_is_successful(): void
    {
        $this->fakeBkash(['payment/execute' => ['statusCode' => 0, 'transactionStatus' => 'Completed']]);

        $this->assertTrue(Bkash::executePayment('P1')->isCompleted());
    }

    public function test_query_payment(): void
    {
        $this->fakeBkash(['query/payment' => ['paymentId' => 'P1', 'transactionStatus' => 'Completed', 'merchantInvoice' => 'INV-1']]);

        $response = Bkash::queryPayment('P1');

        $this->assertTrue($response->isCompleted());
        $this->assertSame('INV-1', $response->invoiceNumber());

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/query/payment')
            && $request['paymentId'] === 'P1');
    }

    public function test_search_transaction(): void
    {
        $this->fakeBkash(['general/search-transaction' => ['trxId' => 'TRX1', 'transactionStatus' => 'Completed']]);

        $this->assertSame('TRX1', Bkash::searchTransaction('TRX1')->trxId);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/general/search-transaction')
            && $request['trxId'] === 'TRX1');
    }

    public function test_it_retries_once_with_fresh_token_on_401(): void
    {
        Http::fakeSequence('*/auth/grant-token')
            ->push($this->tokenResponse('stale-token'))
            ->push($this->tokenResponse('new-token'));

        Http::fakeSequence('*/payment/execute')
            ->push(['errorCode' => '401', 'errorMessage' => 'Unauthorized'], 401)
            ->push(['paymentId' => 'P1', 'transactionStatus' => 'Completed']);

        $this->assertTrue(Bkash::executePayment('P1')->isCompleted());

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payment/execute')
            && $request->hasHeader('Authorization', 'new-token'));
    }

    public function test_connection_error_throws_exception(): void
    {
        Http::fake([
            '*/auth/grant-token' => Http::response($this->tokenResponse()),
            '*/payment/execute' => fn () => throw new ConnectionException('timed out'),
        ]);

        $this->expectException(BkashException::class);
        $this->expectExceptionMessage('Could not connect to bKash [payment/execute]: timed out');

        Bkash::executePayment('P1');
    }

    public function test_non_json_response_throws_exception(): void
    {
        $this->fakeBkash(['payment/execute' => Http::response('<html>Bad Gateway</html>', 502)]);

        $this->expectException(BkashException::class);
        $this->expectExceptionMessage('Invalid response from bKash [payment/execute] with HTTP status 502.');

        Bkash::executePayment('P1');
    }
}
