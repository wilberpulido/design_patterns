<?php

declare(strict_types=1);

namespace DesignPatterns\Concurrency\ProducerConsumer;

// ─── Producer ─────────────────────────────────────────────────────────────────
// The producer generates work and places it in the shared queue.
// It does not know who will consume the entries or when.

class AppLogger
{
    public function __construct(
        private string $name,
        private LogQueue $queue
    ) {}

    public function log(string $level, string $message): void
    {
        $entry = new LogEntry($level, $message, $this->name);
        $accepted = $this->queue->enqueue($entry);

        if ($accepted) {
            echo "[{$this->name}] Produced  [{$level}] \"{$message}\" — buffer: {$this->queue->size()}\n";
        } else {
            // matiz: when the buffer is full the producer must decide: drop, block, or raise.
            // Dropping is acceptable for non-critical logs; blocking is better for critical data.
            // In production, a full buffer should also trigger a monitoring alert.
            echo "[{$this->name}] DROPPED   [{$level}] \"{$message}\" — buffer full!\n";
        }
    }
}
