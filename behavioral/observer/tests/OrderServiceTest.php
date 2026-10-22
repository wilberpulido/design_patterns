<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Behavioral\Observer;

use DesignPatterns\Behavioral\Observer\OrderObserver;
use DesignPatterns\Behavioral\Observer\OrderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderService::class)]
final class OrderServiceTest extends TestCase
{
    private function spy(): OrderObserver
    {
        // Any class that implements the interface can subscribe — the subject never
        // knew this one existed.
        return new class implements OrderObserver {
            public array $received = [];
            public function onOrderPlaced(array $order): void { $this->received[] = $order; }
        };
    }

    public function test_every_attached_observer_is_notified(): void
    {
        $this->expectOutputRegex('/Observer registered/');
        $service = new OrderService();
        $first = $this->spy();
        $second = $this->spy();
        $service->attach($first);
        $service->attach($second);

        $service->placeOrder('Keyboard', 1, 89.90);

        $this->assertCount(1, $first->received);
        $this->assertCount(1, $second->received);
    }

    public function test_detached_observer_stops_receiving_events(): void
    {
        $this->expectOutputRegex('/Observer removed/');
        $service = new OrderService();
        $kept = $this->spy();
        $removed = $this->spy();
        $service->attach($kept);
        $service->attach($removed);

        $service->detach($removed);
        $service->placeOrder('Headset', 2, 149.00);

        $this->assertCount(1, $kept->received);
        $this->assertCount(0, $removed->received);
    }
}
