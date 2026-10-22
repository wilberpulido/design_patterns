<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Cloud\Retry;

use DesignPatterns\Cloud\Retry\PermanentException;
use DesignPatterns\Cloud\Retry\RetryExecutor;
use DesignPatterns\Cloud\Retry\RetryPolicy;
use DesignPatterns\Cloud\Retry\TransientException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryExecutor::class)]
#[CoversClass(RetryPolicy::class)]
final class RetryExecutorTest extends TestCase
{
    // baseDelayMs: 0 → the executor still sleeps between attempts, but for 0ms,
    // so the test suite stays fast without changing the retry logic.
    private function executor(int $maxAttempts = 3): RetryExecutor
    {
        return new RetryExecutor(new RetryPolicy(maxAttempts: $maxAttempts, baseDelayMs: 0));
    }

    public function test_transient_failures_are_retried_until_success(): void
    {
        $this->expectOutputRegex('/Success on attempt 3/');
        $calls = 0;

        $result = $this->executor()->execute(function () use (&$calls) {
            if (++$calls < 3) {
                throw new TransientException('timeout');
            }
            return 'txn_ok';
        });

        $this->assertSame('txn_ok', $result);
        $this->assertSame(3, $calls);
    }

    public function test_permanent_failure_aborts_without_retrying(): void
    {
        $this->expectOutputRegex('/Attempt 1\/3/');
        $calls = 0;

        try {
            $this->executor()->execute(function () use (&$calls) {
                $calls++;
                throw new PermanentException('card declined');
            });
            $this->fail('PermanentException should propagate');
        } catch (PermanentException) {
            $this->assertSame(1, $calls);
        }
    }

    public function test_backoff_grows_exponentially(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 100, backoffMultiplier: 2.0);

        $this->assertSame([100, 200, 400], [$policy->delayMs(1), $policy->delayMs(2), $policy->delayMs(3)]);
    }
}
