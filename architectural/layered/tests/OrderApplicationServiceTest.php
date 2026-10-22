<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Architectural\Layered;

use DesignPatterns\Architectural\Layered\Money;
use DesignPatterns\Architectural\Layered\Order;
use DesignPatterns\Architectural\Layered\OrderApplicationService;
use DesignPatterns\Architectural\Layered\OrderRepository;
use DesignPatterns\Architectural\Layered\PaymentGateway;
use DesignPatterns\Architectural\Layered\PlaceOrderCommand;
use DesignPatterns\Architectural\Layered\StockService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderApplicationService::class)]
#[CoversClass(Order::class)]
final class OrderApplicationServiceTest extends TestCase
{
    private object $orders;
    private object $payments;

    // The application layer depends on interfaces declared for the infrastructure
    // layer, so tests replace MySQL/Stripe/Warehouse with in-memory fakes.
    private function service(bool $inStock = true): OrderApplicationService
    {
        $this->orders = new class implements OrderRepository {
            public array $saved = [];
            public function save(Order $order): void { $this->saved[] = $order; }
        };
        $this->payments = new class implements PaymentGateway {
            public array $charged = [];
            public function charge(string $customerId, Money $amount): string { $this->charged[] = $amount->amount; return 'txn_fake'; }
        };
        $stock = new class($inStock) implements StockService {
            public function __construct(private bool $available) {}
            public function isAvailable(string $productId, int $quantity): bool { return $this->available; }
        };

        return new OrderApplicationService($this->orders, $this->payments, $stock);
    }

    private function command(): PlaceOrderCommand
    {
        return new PlaceOrderCommand('cust-1', [
            ['productId' => 'kb-1', 'name' => 'Keyboard', 'quantity' => 2, 'unitPrice' => 50.0],
        ]);
    }

    public function test_use_case_charges_the_total_and_persists_the_order(): void
    {
        $this->expectOutputRegex('/placed/');

        $result = $this->service()->placeOrder($this->command());

        $this->assertEqualsWithDelta(100.0, $result->total, 0.0001);
        $this->assertSame('txn_fake', $result->transactionId);
        $this->assertCount(1, $this->orders->saved);
    }

    public function test_out_of_stock_stops_the_flow_before_charging(): void
    {
        $this->expectOutputRegex('/.*/');
        $service = $this->service(inStock: false);

        try {
            $service->placeOrder($this->command());
            $this->fail('Expected an out-of-stock exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('out of stock', $e->getMessage());
            $this->assertSame([], $this->payments->charged);   // nobody was charged
            $this->assertSame([], $this->orders->saved);
        }
    }

    public function test_domain_rejects_an_empty_order(): void
    {
        $this->expectException(\LogicException::class);

        (new Order('ord_1', 'cust-1'))->place();
    }
}
