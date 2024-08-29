<?php

namespace Msilabs\Bkash\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Msilabs\Bkash\BkashPayment;

class BkashSandboxController extends Controller
{
    use BkashPayment;

    public function createSandboxPayment()
    {
        return $this->createPayment(10, null, url("/bkash-sandbox/execute-payment"));
    }

    public function executeSandboxPayment()
    {
        if(!request()->paymentID) {
            return redirect($this->createPayment(10, null, url("/bkash-sandbox/execute-payment"))->bkashURL);
        }

        return $this->executePayment(request()->paymentID);
    }
}