<?php

namespace Msilabs\Bkash\Tests\Feature;

use ErrorException;
use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Tests\Fixtures\PaymentController;
use Msilabs\Bkash\Tests\TestCase;

class TokenTest extends TestCase
{
    private $grants = 0;

    private function fakeBkash($expiredToken = null)
    {
        Http::fake(function ($request) use ($expiredToken) {
            if ($this->isTokenRequest($request)) {
                $this->grants++;

                return Http::response(['id_token' => "token-{$this->grants}", 'expires_in' => 3600]);
            }

            if ($request->header('Authorization') === [$expiredToken]) {
                return Http::response(['message' => 'The incoming token has expired'], 401);
            }

            return Http::response(['paymentID' => 'PAY1', 'token' => $request->header('Authorization')[0]]);
        });
    }

    public function test_token_is_reused_from_cache()
    {
        $this->fakeBkash();

        $payment = new PaymentController;
        $payment->createPayment(100);
        $payment->createPayment(100);
        $payment->executePayment('PAY1');

        $this->assertSame(1, $this->grants);
    }

    public function test_get_token_can_skip_the_cache()
    {
        $this->fakeBkash();

        $payment = new PaymentController;

        $this->assertSame('token-1', $payment->getToken());
        $this->assertSame('token-2', $payment->getToken(false));
        $this->assertSame('token-2', $payment->getToken());
    }

    public function test_sandbox_and_live_tokens_are_cached_separately()
    {
        $this->fakeBkash();

        $payment = new PaymentController;
        $payment->getToken();

        config(['bkash.sandbox' => false]);
        $payment->getToken();

        $this->assertSame(2, $this->grants);
    }

    public function test_changing_credentials_fetches_a_new_token()
    {
        $this->fakeBkash();

        $payment = new PaymentController;
        $payment->getToken();

        config(['bkash.app_key' => 'another-app-key']);
        $payment->getToken();

        $this->assertSame(2, $this->grants);
    }

    public function test_expired_token_is_refreshed_and_the_request_retried_once()
    {
        $this->fakeBkash('token-1');

        $response = (new PaymentController)->createPayment(100);

        $this->assertSame('token-2', $response->token);
        $this->assertSame(2, $this->grants);
    }

    public function test_failed_token_request_throws_error_exception()
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 403)]);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Error retrieving bKash token');

        (new PaymentController)->getToken();
    }
}
