<?php

declare(strict_types=1);

namespace DesignPatterns\Concurrency\ProducerConsumer;

// ─── Consumer ─────────────────────────────────────────────────────────────────
// The consumer drains the queue at its own pace.
// It does not know how many producers exist or what they produce.

class LogWriter
{
    private int $processed = 0;

    public function __construct(
        private string $name,
        private LogQueue $queue
    ) {}

    public function consumeOne(): bool
    {
        $entry = $this->queue->dequeue();
        if ($entry === null) {
            return false;
        }
        $this->processed++;
        echo "[{$this->name}] Writing   [{$entry->level}] from {$entry->source}: \"{$entry->message}\"\n";

        return true;
    }

    public function drainAll(): void
    {
        while ($this->consumeOne());
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }
}
