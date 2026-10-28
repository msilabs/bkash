<?php

namespace Msilabs\Bkash\Tests\Fixtures;

use Msilabs\Bkash\BkashPayment;

class PaymentController
{
    use BkashPayment;

    public function useAccount($account)
    {
        $this->setAccount($account);

        return $this;
    }

    public function currentAccount()
    {
        return $this->getAccount();
    }
}
