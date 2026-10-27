<?php

namespace Msilabs\Bkash\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Msilabs\Bkash\Facades\Bkash;
use Msilabs\Bkash\Tests\TestCase;
use Psr\Log\LoggerInterface;

class LoggingTest extends TestCase
{
    public function test_it_logs_with_secrets_masked(): void
    {
        config(['bkash.log.enabled' => true]);
        Bkash::purge();

        $this->fakeBkash(['payment/execute' => ['paymentId' => 'P1', 'transactionStatus' => 'Completed']]);

        $logs = [];

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(function ($message, $context) use (&$logs) {
            $logs[] = compact('message', 'context');
        });

        Log::shouldReceive('channel')->andReturn($logger);

        Bkash::executePayment('P1');

        $this->assertCount(2, $logs);

        [$token, $execute] = $logs;

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

        Log::shouldReceive('channel')->never();

        Bkash::executePayment('P1');
    }
}
