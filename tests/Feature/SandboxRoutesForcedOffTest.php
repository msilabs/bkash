<?php

namespace Msilabs\Bkash\Tests\Feature;

use Msilabs\Bkash\Tests\TestCase;

class SandboxRoutesForcedOffTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('bkash.sandbox_routes', false);
    }

    public function test_routes_can_be_disabled_in_sandbox_mode()
    {
        $this->get('/bkash-sandbox-validation/create-payment')->assertNotFound();
    }
}
