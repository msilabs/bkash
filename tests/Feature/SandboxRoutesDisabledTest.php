<?php

namespace Msilabs\Bkash\Tests\Feature;

use Msilabs\Bkash\Tests\TestCase;

class SandboxRoutesDisabledTest extends TestCase
{
    protected function accounts()
    {
        return [
            'primary' => $this->account('primary', false),
            'secondary' => $this->account('secondary', false),
        ];
    }

    public function test_routes_are_not_registered_when_every_account_is_live()
    {
        $this->get('/bkash-sandbox-validation/create-payment')->assertNotFound();
        $this->get('/bkash-sandbox-validation/execute-payment?paymentID=PAY1')->assertNotFound();
    }
}
