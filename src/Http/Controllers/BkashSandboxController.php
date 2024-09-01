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
        $this->setAccount($account);

        return $this->createPayment(10, null, url("/bkash-sandbox/execute-payment"));
    }

    public function executeSandboxPayment($account = 'primary')
    {
        $this->setAccount($account);

        $paymentID = request()->paymentID;

        if(!$paymentID) {
            $invoice_number = (string) (time() . uniqid());

            $callback_url = url("/bkash-sandbox/execute-payment/" . $this->getAccount());

            $data = $this->createPayment(10, $invoice_number, $callback_url);

            return redirect($data->bkashURL);
        }

        return $this->executePayment($paymentID);
    }
}