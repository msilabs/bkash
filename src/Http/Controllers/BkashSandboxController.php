<?php

namespace Msilabs\Bkash\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Msilabs\Bkash\BkashClient;
use Msilabs\Bkash\BkashManager;

class BkashSandboxController extends Controller
{
    public function __construct(protected BkashManager $bkash) {}

    public function create(?string $account = null)
    {
        $client = $this->client($account);

        $response = $client->createPayment(
            amount: 10,
            invoiceNumber: 'INV'.time(),
            callbackUrl: route('bkash.sandbox.callback', ['account' => $client->name()]),
        );

        if ($response->failed() || ! $response->redirectUrl()) {
            return response()->json($response, 422);
        }

        return redirect()->away($response->redirectUrl());
    }

    public function callback(Request $request, ?string $account = null)
    {
        $client = $this->client($account);

        $status = $request->query('status');
        $paymentId = $request->query('paymentId', $request->query('paymentID'));

        if ($status !== 'success' || ! $paymentId) {
            return response()->json(['status' => $status, 'paymentId' => $paymentId], 422);
        }

        return response()->json($client->executePayment($paymentId));
    }

    public function query(string $paymentId, ?string $account = null)
    {
        return response()->json($this->client($account)->queryPayment($paymentId));
    }

    protected function client(?string $account): BkashClient
    {
        abort_unless(in_array($account ?? $this->bkash->getDefaultAccount(), $this->bkash->accounts(), true), 404);

        $client = $this->bkash->account($account);

        abort_unless($client->isSandbox(), 404);

        return $client;
    }
}
