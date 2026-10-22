<?php

declare(strict_types=1);

namespace DesignPatterns\Concurrency\ProducerConsumer;

// ─── Shared buffer ────────────────────────────────────────────────────────────
// The queue is the heart of the pattern. It decouples producers from consumers
// so neither side needs to know about the other.

class LogQueue
{
    private array $entries = [];

    public function __construct(private int $maxSize = 8) {}

    // Returns false if the buffer is full (backpressure signal).
    public function enqueue(LogEntry $entry): bool
    {
        if (count($this->entries) >= $this->maxSize) {
            return false; // buffer full — producer must back off
        }
        $this->entries[] = $entry;
        return true;
    }

    public function dequeue(): ?LogEntry
    {
        return array_shift($this->entries) ?? null;
    }

    public function size(): int  { return count($this->entries); }
    public function isEmpty(): bool { return empty($this->entries); }
}
