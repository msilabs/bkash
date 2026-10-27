<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Facades\Bkash;
use Msilabs\Bkash\Tests\TestCase;

class RefundTest extends TestCase
{
    public function test_refund(): void
    {
        $this->fakeBkash([
            'refund/payment/transaction' => [
                'originalTrxId' => 'TRX1',
                'refundTrxId' => 'RTRX1',
                'refundTransactionStatus' => 'Completed',
                'refundAmount' => '50.00',
            ],
        ]);

        $response = Bkash::refund('P1', 'TRX1', 50, 'Product damaged', 'SKU-9');

        $this->assertTrue($response->isCompleted());
        $this->assertSame('RTRX1', $response->refundTrxId);

        Http::assertSent(fn (Request $request) => $request->url() === self::SANDBOX.'/refund/payment/transaction'
            && $request->data() === [
                'paymentId' => 'P1',
                'trxId' => 'TRX1',
                'refundAmount' => '50.00',
                'sku' => 'SKU-9',
                'reason' => 'Product damaged',
            ]);
    }

    public function test_refund_default_sku(): void
    {
        $this->fakeBkash(['refund/payment/transaction' => ['refundTransactionStatus' => 'Completed']]);

        Bkash::refund('P1', 'TRX1', '10.5', 'Customer request');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/refund/payment/transaction')
            && $request['sku'] === 'N/A'
            && $request['refundAmount'] === '10.50');
    }

    public function test_refund_status(): void
    {
        $this->fakeBkash([
            'refund/payment/status' => [
                'originalTrxId' => 'TRX1',
                'refundTransactions' => [
                    ['refundTrxId' => 'RTRX1', 'refundTransactionStatus' => 'Completed', 'refundAmount' => '50.00'],
                ],
            ],
        ]);

        $response = Bkash::refundStatus('P1', 'TRX1');

        $this->assertTrue($response->successful());
        $this->assertSame('RTRX1', $response->get('refundTransactions.0.refundTrxId'));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/refund/payment/status')
            && $request->data() === ['paymentId' => 'P1', 'trxId' => 'TRX1']);
    }
}
