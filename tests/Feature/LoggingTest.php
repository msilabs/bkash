<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Msilabs\Bkash\Facades\Bkash;
use Msilabs\Bkash\Tests\TestCase;

class LoggingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('logging.channels.bkash-test', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ]);

        $app['config']->set('bkash.log.channel', 'bkash-test');
    }

    public function test_it_logs_with_secrets_masked(): void
    {
        config(['bkash.log.enabled' => true]);
        Bkash::purge();

        $this->fakeBkash(['payment/execute' => ['paymentId' => 'P1', 'transactionStatus' => 'Completed']]);

        Bkash::executePayment('P1');

        $records = $this->logHandler()->getRecords();

        $this->assertCount(2, $records);

        [$token, $execute] = $records;

        $this->assertSame('bKash [primary] auth/grant-token', $token['message']);
        $this->assertSame('********', $token['context']['headers']['password']);
        $this->assertSame('********', $token['context']['request']['app_secret']);
        $this->assertSame('primary-key', $token['context']['request']['app_key']);
        $this->assertSame('********', $token['context']['response']['id_token']);
        $this->assertSame('********', $token['context']['response']['refresh_token']);

        $this->assertSame('bKash [primary] payment/execute', $execute['message']);
        $this->assertSame('********', $execute['context']['headers']['Authorization']);
        $this->assertSame(['paymentId' => 'P1'], $execute['context']['request']);
        $this->assertSame('Completed', $execute['context']['response']['transactionStatus']);
    }

    public function test_it_does_not_log_when_disabled(): void
    {
        $this->fakeBkash(['payment/execute' => ['transactionStatus' => 'Completed']]);

        Bkash::executePayment('P1');

        $this->assertCount(0, $this->logHandler()->getRecords());
    }

    protected function logHandler(): TestHandler
    {
        return Log::channel('bkash-test')->getLogger()->getHandlers()[0];
    }
}
