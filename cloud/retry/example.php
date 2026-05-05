<?php

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

// ─── Exceptions ───────────────────────────────────────────────────────────────
// Distinguishing exception types is what allows the executor to decide
// whether to retry or abort immediately.

class TransientException extends \RuntimeException {}  // temporary — worth retrying
class PermanentException extends \RuntimeException {}  // permanent — retrying won't help

// ─── Retry Policy ─────────────────────────────────────────────────────────────

class RetryPolicy
{
    public function __construct(
        public readonly int   $maxAttempts       = 3,
        public readonly int   $baseDelayMs       = 100,
        public readonly float $backoffMultiplier = 2.0
    ) {}

    // Delay grows exponentially: 100ms → 200ms → 400ms
    public function delayMs(int $attempt): int
    {
        return (int) ($this->baseDelayMs * ($this->backoffMultiplier ** ($attempt - 1)));
    }
}

// ─── Retry Executor ───────────────────────────────────────────────────────────
// The executor is generic — it knows nothing about payments or HTTP.
// Any callable can be wrapped and retried with the same logic.

class RetryExecutor
{
    public function __construct(private RetryPolicy $policy) {}

    public function execute(callable $operation): mixed
    {
        $attempt = 1;

        while (true) {
            try {
                echo "[RetryExecutor] Attempt {$attempt}/{$this->policy->maxAttempts}...\n";
                $result = $operation();
                echo "[RetryExecutor] Success on attempt {$attempt}.\n";
                return $result;

            } catch (TransientException $e) {
                if ($attempt >= $this->policy->maxAttempts) {
                    echo "[RetryExecutor] All {$this->policy->maxAttempts} attempts exhausted. Giving up.\n";
                    throw $e;
                }
                $delay = $this->policy->delayMs($attempt);
                echo "[RetryExecutor] Transient failure: \"{$e->getMessage()}\". Retrying in {$delay}ms...\n";
                usleep($delay * 1000);
                $attempt++;

            } catch (PermanentException $e) {
                // matiz: non-retryable errors must abort immediately — no delay, no further attempts.
                // Retrying a "card declined" or "invalid credentials" error wastes time
                // and could lock accounts or trigger fraud detection.
                echo "[RetryExecutor] Permanent failure: \"{$e->getMessage()}\". Aborting immediately.\n";
                throw $e;
            }
        }
    }
}

// ─── Payment Gateway ──────────────────────────────────────────────────────────
// Simulates a flaky payment provider that times out under load.

class StripeGateway
{
    private int $remainingFailures;

    public function __construct(int $simulatedFailures = 0)
    {
        $this->remainingFailures = $simulatedFailures;
    }

    public function charge(string $cardToken, float $amount): string
    {
        echo "[StripeGateway] Charging \${$amount} on token {$cardToken}...\n";

        if ($this->remainingFailures > 0) {
            $this->remainingFailures--;
            throw new TransientException("Connection timeout to Stripe API");
        }

        $txnId = 'txn_' . strtoupper(substr(md5($cardToken . microtime()), 0, 10));
        echo "[StripeGateway] Charge approved. Transaction ID: {$txnId}\n";
        return $txnId;
    }

    public function chargeDeclinedCard(string $cardToken, float $amount): string
    {
        echo "[StripeGateway] Charging \${$amount} on token {$cardToken}...\n";
        throw new PermanentException("Card declined: insufficient funds (do not retry)");
    }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

echo "=== Payment Gateway — Retry Pattern ===\n\n";

$policy   = new RetryPolicy(maxAttempts: 3, baseDelayMs: 100, backoffMultiplier: 2.0);
$executor = new RetryExecutor($policy);

// Case 1: success on first attempt
echo "--- Case 1: No failures ---\n";
$gateway = new StripeGateway(simulatedFailures: 0);
$txn = $executor->execute(fn() => $gateway->charge('tok_visa_4242', 99.00));
echo "Result: {$txn}\n\n";

// Case 2: fails once, succeeds on second attempt
echo "--- Case 2: One transient failure ---\n";
$gateway = new StripeGateway(simulatedFailures: 1);
$txn = $executor->execute(fn() => $gateway->charge('tok_visa_1234', 49.99));
echo "Result: {$txn}\n\n";

// Case 3: fails twice, succeeds on third attempt
echo "--- Case 3: Two transient failures ---\n";
$gateway = new StripeGateway(simulatedFailures: 2);
$txn = $executor->execute(fn() => $gateway->charge('tok_mastercard_5555', 199.00));
echo "Result: {$txn}\n\n";

// Case 4: all attempts exhausted
echo "--- Case 4: All attempts exhausted ---\n";
$gateway = new StripeGateway(simulatedFailures: 99);
try {
    $executor->execute(fn() => $gateway->charge('tok_visa_9999', 299.00));
} catch (TransientException $e) {
    echo "Final error: {$e->getMessage()}\n\n";
}

// Case 5: non-retryable error — aborts immediately
echo "--- Case 5: Permanent error (card declined) ---\n";
$gateway = new StripeGateway();
try {
    $executor->execute(fn() => $gateway->chargeDeclinedCard('tok_declined', 59.00));
} catch (PermanentException $e) {
    echo "Final error: {$e->getMessage()}\n";
}
