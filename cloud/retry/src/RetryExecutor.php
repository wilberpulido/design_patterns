<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\Retry;

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
