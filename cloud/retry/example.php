<?php

declare(strict_types=1);

/**
 * Laravel note:
 *
 * How Laravel applies Retry natively:
 * - `retry(int $times, callable $callback, int $sleepMs)` — global helper that retries
 *   any callable. Example: retry(3, fn() => Http::get($url), 100).
 * - HTTP client: `Http::retry(3, 100)->get($url)` — built-in retry with delay.
 * - Queued jobs: set `$tries = 3` and `retryAfter()` on the Job class.
 *   Laravel automatically retries failed jobs up to that limit.
 *
 * Where it makes sense to apply Retry yourself in a Laravel project:
 * - Wrapping third-party SDK calls that don't support retry natively
 *   (e.g. a payment SDK that throws exceptions on timeout).
 * - Console commands that sync data from flaky external APIs.
 * - Custom retry policies with exponential backoff or non-retryable error filtering
 *   that go beyond what the built-in helpers offer.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/TransientException.php     Error classification — transient = retry, permanent = abort
//   src/PermanentException.php
//   src/RetryPolicy.php            Policy — max attempts and exponential backoff delays
//   src/RetryExecutor.php          Executor — the retry loop, wraps ANY callable
//   src/StripeGateway.php          Flaky remote dependency (simulated failures)
// Executable guarantees of the pattern: tests/RetryExecutorTest.php

require __DIR__.'/../../vendor/autoload.php';

use DesignPatterns\Cloud\Retry\PermanentException;
use DesignPatterns\Cloud\Retry\RetryExecutor;
use DesignPatterns\Cloud\Retry\RetryPolicy;
use DesignPatterns\Cloud\Retry\StripeGateway;
use DesignPatterns\Cloud\Retry\TransientException;

// ─── Bootstrap ────────────────────────────────────────────────────────────────

echo "=== Payment Gateway — Retry Pattern ===\n\n";

$policy = new RetryPolicy(maxAttempts: 3, baseDelayMs: 100, backoffMultiplier: 2.0);
$executor = new RetryExecutor($policy);

// Case 1: success on first attempt
echo "--- Case 1: No failures ---\n";
$gateway = new StripeGateway(simulatedFailures: 0);
$txn = $executor->execute(fn () => $gateway->charge('tok_visa_4242', 99.00));
echo "Result: {$txn}\n\n";

// Case 2: fails once, succeeds on second attempt
echo "--- Case 2: One transient failure ---\n";
$gateway = new StripeGateway(simulatedFailures: 1);
$txn = $executor->execute(fn () => $gateway->charge('tok_visa_1234', 49.99));
echo "Result: {$txn}\n\n";

// Case 3: fails twice, succeeds on third attempt
echo "--- Case 3: Two transient failures ---\n";
$gateway = new StripeGateway(simulatedFailures: 2);
$txn = $executor->execute(fn () => $gateway->charge('tok_mastercard_5555', 199.00));
echo "Result: {$txn}\n\n";

// Case 4: all attempts exhausted
echo "--- Case 4: All attempts exhausted ---\n";
$gateway = new StripeGateway(simulatedFailures: 99);
try {
    $executor->execute(fn () => $gateway->charge('tok_visa_9999', 299.00));
} catch (TransientException $e) {
    echo "Final error: {$e->getMessage()}\n\n";
}

// Case 5: non-retryable error — aborts immediately
echo "--- Case 5: Permanent error (card declined) ---\n";
$gateway = new StripeGateway;
try {
    $executor->execute(fn () => $gateway->chargeDeclinedCard('tok_declined', 59.00));
} catch (PermanentException $e) {
    echo "Final error: {$e->getMessage()}\n";
}
