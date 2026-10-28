<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Msilabs\Bkash\Tests\Fixtures\PaymentController;
use Msilabs\Bkash\Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_primary_is_the_default_account()
    {
        $this->assertSame('primary', (new PaymentController)->currentAccount());
    }

    public function test_selected_account_does_not_leak_into_other_instances()
    {
        $this->assertSame('secondary', (new PaymentController)->useAccount('secondary')->currentAccount());
        $this->assertSame('primary', (new PaymentController)->currentAccount());
    }

    public function test_unknown_account_keeps_the_current_one_and_logs_a_warning()
    {
        Log::shouldReceive('warning')->once();

        $payment = (new PaymentController)->useAccount('secondary')->useAccount('typo');

        $this->assertSame('secondary', $payment->currentAccount());
    }
}
