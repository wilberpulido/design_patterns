<?php

declare(strict_types=1);

namespace DesignPatterns\Concurrency\ProducerConsumer;

// ─── Data ─────────────────────────────────────────────────────────────────────

class LogEntry
{
    public readonly string $timestamp;

    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly string $source
    ) {
        $this->timestamp = date('H:i:s.') . str_pad((string)((int)(microtime(true) * 1000) % 1000), 3, '0', STR_PAD_LEFT);
    }
}
