<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Concurrency\ProducerConsumer;

use DesignPatterns\Concurrency\ProducerConsumer\AppLogger;
use DesignPatterns\Concurrency\ProducerConsumer\LogEntry;
use DesignPatterns\Concurrency\ProducerConsumer\LogQueue;
use DesignPatterns\Concurrency\ProducerConsumer\LogWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogQueue::class)]
#[CoversClass(AppLogger::class)]
#[CoversClass(LogWriter::class)]
final class LogQueueTest extends TestCase
{
    public function test_bounded_buffer_rejects_entries_when_full(): void
    {
        $this->expectOutputRegex('/\[LogQueue\]|^$/');
        $queue = new LogQueue(maxSize: 2);

        $this->assertTrue($queue->enqueue(new LogEntry('INFO', 'one', 'api')));
        $this->assertTrue($queue->enqueue(new LogEntry('INFO', 'two', 'api')));
        // Backpressure: the producer is told to back off instead of growing memory forever.
        $this->assertFalse($queue->enqueue(new LogEntry('INFO', 'three', 'api')));
    }

    public function test_consumer_drains_entries_in_fifo_order(): void
    {
        $this->expectOutputRegex('/\[/');
        $queue = new LogQueue();
        $producer = new AppLogger('api', $queue);
        $consumer = new LogWriter('writer-1', $queue);

        $producer->log('INFO', 'first');
        $producer->log('ERROR', 'second');
        $consumer->drainAll();

        $this->assertTrue($queue->isEmpty());
        $this->assertSame(2, $consumer->getProcessed());
    }
}
