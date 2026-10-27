<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Facades\Bkash;
use Msilabs\Bkash\Tests\TestCase;

class AgreementTest extends TestCase
{
    public function test_create_agreement(): void
    {
        $this->fakeBkash([
            'agreement/create' => ['paymentId' => 'P1', 'agreementStatus' => 'Initiated', 'bkashURL' => 'https://pay'],
        ]);

        $response = Bkash::createAgreement('01700000000', 'https://cb');

        $this->assertSame('https://pay', $response->redirectUrl());
        $this->assertFalse($response->isCompleted());

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/agreement/create')
            && $request->data() === ['payerReference' => '01700000000', 'callbackURL' => 'https://cb']);
    }

    public function test_execute_query_and_cancel_agreement(): void
    {
        $this->fakeBkash([
            'agreement/execute' => ['agreementId' => 'A1', 'agreementStatus' => 'Completed'],
            'query/agreement' => ['agreementId' => 'A1', 'agreementStatus' => 'Completed'],
            'agreement/cancel' => ['agreementId' => 'A1', 'agreementStatus' => 'Cancelled'],
        ]);

        $this->assertTrue(Bkash::executeAgreement('A1')->isCompleted());
        $this->assertTrue(Bkash::queryAgreement('A1')->isCompleted());
        $this->assertSame('Cancelled', Bkash::cancelAgreement('A1')->agreementStatus);

        foreach (['agreement/execute', 'query/agreement', 'agreement/cancel'] as $endpoint) {
            Http::assertSent(fn (Request $request) => str_ends_with($request->url(), $endpoint)
                && $request->data() === ['agreementId' => 'A1']);
        }
    }

    public function test_payment_with_agreement(): void
    {
        $this->fakeBkash([
            'payment-with-agreement/create' => ['paymentId' => 'P2', 'bkashURL' => 'https://pay'],
            'payment-with-agreement/execute' => ['paymentId' => 'P2', 'trxId' => 'TRX2', 'transactionStatus' => 'Completed'],
        ]);

        Bkash::createPaymentWithAgreement('A1', 200, 'INV-3', 'https://cb');
        $response = Bkash::executePaymentWithAgreement('P2', 'A1');

        $this->assertTrue($response->isCompleted());

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payment-with-agreement/create')
            && $request->data() === [
                'agreementId' => 'A1',
                'callbackURL' => 'https://cb',
                'amount' => '200.00',
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => 'INV-3',
            ]);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payment-with-agreement/execute')
            && $request->data() === ['paymentId' => 'P2', 'agreementId' => 'A1']);
    }
}
