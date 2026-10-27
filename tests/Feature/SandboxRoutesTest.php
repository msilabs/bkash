<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Msilabs\Bkash\Tests\TestCase;

class SandboxRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('bkash.sandbox_routes', true);
    }

    public function test_create_redirects_to_bkash(): void
    {
        $this->fakeBkash(['payment/create' => ['paymentId' => 'P1', 'bkashURL' => 'https://sandbox.pay/P1']]);

        $this->get('/bkash-sandbox/create')->assertRedirect('https://sandbox.pay/P1');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payment/create')
            && $request['callbackURL'] === url('/bkash-sandbox/callback/primary'));
    }

    public function test_callback_executes_payment(): void
    {
        $this->fakeBkash(['payment/execute' => ['paymentId' => 'P1', 'trxId' => 'TRX1', 'transactionStatus' => 'Completed']]);

        $this->get('/bkash-sandbox/callback/primary?paymentId=P1&status=success')
            ->assertOk()
            ->assertJson(['trxId' => 'TRX1', 'transactionStatus' => 'Completed']);
    }

    public function test_callback_with_cancel_status_does_not_execute(): void
    {
        Http::fake();

        $this->get('/bkash-sandbox/callback?paymentId=P1&status=cancel')
            ->assertStatus(422)
            ->assertJson(['status' => 'cancel']);

        Http::assertNothingSent();
    }

    public function test_production_account_is_not_available(): void
    {
        $this->get('/bkash-sandbox/create/secondary')->assertNotFound();
    }

    public function test_unknown_account_is_not_found(): void
    {
        $this->get('/bkash-sandbox/create/unknown')->assertNotFound();
    }
}
