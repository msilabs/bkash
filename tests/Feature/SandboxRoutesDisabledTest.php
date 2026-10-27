<?php

namespace Msilabs\Bkash\Tests\Feature;

use Msilabs\Bkash\Tests\TestCase;

class SandboxRoutesDisabledTest extends TestCase
{
    public function test_routes_are_not_registered_by_default(): void
    {
        $this->get('/bkash-sandbox/create')->assertNotFound();
    }
}
