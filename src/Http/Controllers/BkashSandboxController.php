<?php

namespace Msilabs\Bkash\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Msilabs\Bkash\BkashPayment;

class BkashSandboxController extends Controller
{
    use BkashPayment;

    public function createSandboxPayment($account = 'primary')
    {
        $this->setSandboxAccount($account);

        $invoice_number = (string) (time() . uniqid());

        $callback_url = url("/bkash-sandbox-validation/execute-payment/" . $this->getAccount());

        $data = $this->createPayment(10, $invoice_number, $callback_url);

        return response()->json($data);
    }

    public function executeSandboxPayment($account = 'primary')
    {
        $this->setSandboxAccount($account);

        $paymentID = request()->paymentID;

        if(!$paymentID) {
            $data = $this->createSandboxPayment($this->getAccount())->getData();

            return redirect($data->bkashURL);
        }

        return response()->json($this->executePayment($paymentID));
    }

    private function setSandboxAccount($account)
    {
        // Never let these public routes touch a live account
        if (!config()->has("bkash.accounts.{$account}") || !config("bkash.accounts.{$account}.sandbox")) {
            abort(404);
        }

        $this->setAccount($account);
    }
}
