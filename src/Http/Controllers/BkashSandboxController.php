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
        $this->ensureSandbox();

        return response()->json($this->createPayment(10, null, url("/bkash-sandbox/execute-payment")));
    }

    public function executeSandboxPayment()
    {
        $this->ensureSandbox();

        if(!request()->paymentID) {
            return redirect($this->createPayment(10, null, url("/bkash-sandbox/execute-payment"))->bkashURL);
        }

        return response()->json($this->executePayment(request()->paymentID));
    }

    private function ensureSandbox()
    {
        // Never let these public routes touch live credentials
        if (!config('bkash.sandbox')) {
            abort(404);
        }
    }
}
