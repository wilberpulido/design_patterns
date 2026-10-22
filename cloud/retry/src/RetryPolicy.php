<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\Retry;

// ─── Retry Policy ─────────────────────────────────────────────────────────────

class RetryPolicy
{
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $baseDelayMs = 100,
        public readonly float $backoffMultiplier = 2.0
    ) {}

    // Delay grows exponentially: 100ms → 200ms → 400ms
    public function delayMs(int $attempt): int
    {
        return (int) ($this->baseDelayMs * ($this->backoffMultiplier ** ($attempt - 1)));
    }
}
