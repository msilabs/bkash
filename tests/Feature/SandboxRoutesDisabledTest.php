<?php

namespace Msilabs\Bkash\Tests\Feature;

use Msilabs\Bkash\Tests\TestCase;

class SandboxRoutesDisabledTest extends TestCase
{
    protected $sandbox = false;

    public function test_routes_are_not_registered_in_live_mode()
    {
        $this->get('/bkash-sandbox/create-payment')->assertNotFound();
        $this->get('/bkash-sandbox/execute-payment?paymentID=PAY1')->assertNotFound();
    }
}
