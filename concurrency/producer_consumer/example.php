<?php

/**
 * Laravel note:
 *
 * How Laravel applies Producer-Consumer natively:
 * - Laravel Queues ARE a production-grade Producer-Consumer implementation.
 * - Producers: dispatch(new MyJob()), Bus::dispatch(), $model->dispatchAfterResponse()
 * - Consumers: `php artisan queue:work` — each worker process is a consumer.
 * - The queue driver (Redis, SQS, database) acts as the shared bounded buffer.
 * - Laravel Horizon auto-scales workers based on queue depth (consumer scaling).
 *
 * Where it makes sense to apply Producer-Consumer yourself in a Laravel project:
 * - In-memory processing pipelines within a single request: streaming a large CSV
 *   through a transform/validate/insert pipeline without loading it all into memory.
 * - Generator-based pipelines: PHP generators act as lazy producers that feed
 *   a processing loop (the consumer), controlling memory usage on large datasets.
 */

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

// ─── Producer ─────────────────────────────────────────────────────────────────
// The producer generates work and places it in the shared queue.
// It does not know who will consume the entries or when.

class AppLogger
{
    public function __construct(
        private string   $name,
        private LogQueue $queue
    ) {}

    public function log(string $level, string $message): void
    {
        $entry    = new LogEntry($level, $message, $this->name);
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

// ─── Consumer ─────────────────────────────────────────────────────────────────
// The consumer drains the queue at its own pace.
// It does not know how many producers exist or what they produce.

class LogWriter
{
    private int $processed = 0;

    public function __construct(
        private string   $name,
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

    public function getProcessed(): int { return $this->processed; }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────
// PHP runs in a single thread, so we simulate interleaved production and
// consumption by alternating rounds — showing how the queue buffers bursts.
// In production, producers and consumers would run as separate processes.

echo "=== Log Aggregation — Producer-Consumer Pattern ===\n\n";

$queue  = new LogQueue(maxSize: 8);
$writer = new LogWriter('LogWriter', $queue);

$apiLogger = new AppLogger('ApiService',     $queue);
$jobLogger = new AppLogger('JobWorker',      $queue);
$dbLogger  = new AppLogger('DatabaseLayer',  $queue);

// Round 1: burst of log entries from multiple producers
echo "--- Round 1: production burst ---\n";
$apiLogger->log('INFO',  'POST /api/orders — 201 Created');
$apiLogger->log('INFO',  'POST /api/orders — 201 Created');
$jobLogger->log('INFO',  'Job SyncInventory started');
$dbLogger ->log('WARN',  'Slow query detected: 1.4s');
$apiLogger->log('ERROR', 'POST /api/payments — 502 Bad Gateway');
$jobLogger->log('INFO',  'Job SyncInventory completed');

// Consumer drains some entries
echo "\n--- Consumer draining ---\n";
for ($i = 0; $i < 3; $i++) {
    $writer->consumeOne();
}

// Round 2: more production while consumer is still working
echo "\n--- Round 2: more entries ---\n";
$dbLogger ->log('INFO',  'Connection pool: 12/20 active');
$apiLogger->log('INFO',  'GET /api/products — 200 OK');
$jobLogger->log('WARN',  'Job SendEmails retrying (attempt 2)');
$apiLogger->log('INFO',  'GET /api/users — 200 OK');
$apiLogger->log('INFO',  'GET /api/users — 200 OK');
$apiLogger->log('INFO',  'GET /api/users/1 — 200 OK'); // may drop if buffer full

// Consumer drains everything
echo "\n--- Consumer draining all remaining ---\n";
$writer->drainAll();

echo "\n[LogWriter] Total entries written: {$writer->getProcessed()}\n";
