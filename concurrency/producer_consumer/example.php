<?php

declare(strict_types=1);

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

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/LogEntry.php               Message — what travels from producers to consumers
//   src/LogQueue.php               Bounded buffer — decouples both sides, rejects when full
//   src/AppLogger.php              Producer — enqueues entries without waiting for the write
//   src/LogWriter.php              Consumer — dequeues and processes at its own pace
// Executable guarantees of the pattern: tests/LogQueueTest.php

require __DIR__.'/../../vendor/autoload.php';

use DesignPatterns\Concurrency\ProducerConsumer\AppLogger;
use DesignPatterns\Concurrency\ProducerConsumer\LogQueue;
use DesignPatterns\Concurrency\ProducerConsumer\LogWriter;

// ─── Bootstrap ────────────────────────────────────────────────────────────────
// PHP runs in a single thread, so we simulate interleaved production and
// consumption by alternating rounds — showing how the queue buffers bursts.
// In production, producers and consumers would run as separate processes.

echo "=== Log Aggregation — Producer-Consumer Pattern ===\n\n";

$queue = new LogQueue(maxSize: 8);
$writer = new LogWriter('LogWriter', $queue);

$apiLogger = new AppLogger('ApiService', $queue);
$jobLogger = new AppLogger('JobWorker', $queue);
$dbLogger = new AppLogger('DatabaseLayer', $queue);

// Round 1: burst of log entries from multiple producers
echo "--- Round 1: production burst ---\n";
$apiLogger->log('INFO', 'POST /api/orders — 201 Created');
$apiLogger->log('INFO', 'POST /api/orders — 201 Created');
$jobLogger->log('INFO', 'Job SyncInventory started');
$dbLogger->log('WARN', 'Slow query detected: 1.4s');
$apiLogger->log('ERROR', 'POST /api/payments — 502 Bad Gateway');
$jobLogger->log('INFO', 'Job SyncInventory completed');

// Consumer drains some entries
echo "\n--- Consumer draining ---\n";
for ($i = 0; $i < 3; $i++) {
    $writer->consumeOne();
}

// Round 2: more production while consumer is still working
echo "\n--- Round 2: more entries ---\n";
$dbLogger->log('INFO', 'Connection pool: 12/20 active');
$apiLogger->log('INFO', 'GET /api/products — 200 OK');
$jobLogger->log('WARN', 'Job SendEmails retrying (attempt 2)');
$apiLogger->log('INFO', 'GET /api/users — 200 OK');
$apiLogger->log('INFO', 'GET /api/users — 200 OK');
$apiLogger->log('INFO', 'GET /api/users/1 — 200 OK'); // may drop if buffer full

// Consumer drains everything
echo "\n--- Consumer draining all remaining ---\n";
$writer->drainAll();

echo "\n[LogWriter] Total entries written: {$writer->getProcessed()}\n";
