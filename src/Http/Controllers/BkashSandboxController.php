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

        $invoice_number = (string) (time() . uniqid());

        $callback_url = url("/bkash-sandbox-validation/execute-payment/" . $this->getAccount());

        $data = $this->createPayment(10, $invoice_number, $callback_url);

        return $data;
    }

    public function executeSandboxPayment($account = 'primary')
    {
        $this->setAccount($account);

        $paymentID = request()->paymentID;

        if(!$paymentID) {
            $data = $this->createSandboxPayment($this->getAccount());

            return redirect($data->bkashURL);
        }

        return $this->executePayment($paymentID);
    }
}