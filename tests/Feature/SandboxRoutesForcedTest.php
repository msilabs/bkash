<?php

namespace Msilabs\Bkash\Tests\Feature;

use Msilabs\Bkash\Tests\TestCase;

class SandboxRoutesForcedTest extends TestCase
{
    protected $sandbox = false;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('bkash.sandbox_routes', true);
    }

    public function test_forced_routes_still_refuse_live_credentials()
    {
        $this->get('/bkash-sandbox/create-payment')->assertNotFound();
        $this->get('/bkash-sandbox/execute-payment?paymentID=PAY1')->assertNotFound();
    }
}
