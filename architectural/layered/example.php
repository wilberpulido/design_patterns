<?php

/*
 * Laravel:
 * 1. Laravel naturally maps to Layered Architecture out of the box:
 *    - Presentation → Controllers, Form Requests, Resources (API responses)
 *    - Application  → Service classes (not built-in, but the conventional place)
 *    - Domain       → Eloquent Models with business methods, Value Objects
 *    - Infrastructure → Eloquent queries, Mail, Queue, Storage facades
 *    The framework doesn't enforce strict layering, but nothing prevents it.
 *
 * 2. Apply Layered Architecture yourself in Laravel when:
 *    - A controller exceeds routing concerns — extract an Application Service class
 *    - Business rules are scattered across controllers, observers, and models
 *    - You want to unit-test domain logic without HTTP, DB, or mail setup
 *    - You're building a domain-rich app where Eloquent Active Record is insufficient
 */

// ============================================================
// DOMAIN LAYER — business rules, entities, no external deps
// ============================================================

class Money
{
    public function __construct(
        public readonly float  $amount,
        public readonly string $currency = 'USD'
    ) {}

    public function add(Money $other): Money
    {
        return new Money($this->amount + $other->amount, $this->currency);
    }
}

class OrderItem
{
    public function __construct(
        public readonly string $productId,
        public readonly string $name,
        public readonly int    $quantity,
        public readonly Money  $unitPrice
    ) {}

    public function subtotal(): Money
    {
        return new Money($this->unitPrice->amount * $this->quantity, $this->unitPrice->currency);
    }
}

class Order
{
    private array  $items  = [];
    private string $status = 'pending';

    public function __construct(
        public readonly string $id,
        public readonly string $customerId
    ) {}

    public function addItem(OrderItem $item): void
    {
        // Domain rule: placed orders are immutable
        if ($this->status !== 'pending') {
            throw new \LogicException("Cannot modify a {$this->status} order.");
        }
        $this->items[] = $item;
        echo "[Order] Added '{$item->name}' x{$item->quantity}\n";
    }

    public function place(): void
    {
        if (empty($this->items)) {
            throw new \LogicException("Cannot place an empty order.");
        }
        $this->status = 'placed';
        echo "[Order] Order {$this->id} placed — status: placed\n";
    }

    public function total(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            new Money(0)
        );
    }

    public function getStatus(): string { return $this->status; }
}

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment gateway, stock service
// ============================================================

interface OrderRepository
{
    public function save(Order $order): void;
}

interface PaymentGateway
{
    public function charge(string $customerId, Money $amount): string; // returns transaction ID
}

interface StockService
{
    public function isAvailable(string $productId, int $quantity): bool;
}

class MySqlOrderRepository implements OrderRepository
{
    public function save(Order $order): void
    {
        echo "[MySqlOrderRepository] INSERT/UPDATE order '{$order->id}' status='{$order->getStatus()}'\n";
    }
}

class StripePaymentGateway implements PaymentGateway
{
    public function charge(string $customerId, Money $amount): string
    {
        echo "[StripePaymentGateway] Charging customer '{$customerId}' \${$amount->amount} via Stripe...\n";
        return 'txn_' . substr(md5(uniqid()), 0, 8);
    }
}

class WarehouseStockService implements StockService
{
    public function isAvailable(string $productId, int $quantity): bool
    {
        echo "[WarehouseStockService] Checking stock for '{$productId}' (qty: {$quantity})...\n";
        return true;
    }
}

// ============================================================
// APPLICATION LAYER — use cases, DTOs, orchestration
// ============================================================

class PlaceOrderCommand
{
    public function __construct(
        public readonly string $customerId,
        public readonly array  $items  // [['productId', 'name', 'quantity', 'unitPrice'], ...]
    ) {}
}

class PlaceOrderResult
{
    public function __construct(
        public readonly string $orderId,
        public readonly float  $total,
        public readonly string $transactionId
    ) {}
}

class OrderApplicationService
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentGateway  $payments,
        private StockService    $stock
    ) {}

    public function placeOrder(PlaceOrderCommand $cmd): PlaceOrderResult
    {
        echo "\n[OrderApplicationService] Starting PlaceOrder use case...\n";

        $order = new Order(uniqid('ord_'), $cmd->customerId);

        foreach ($cmd->items as $item) {
            // Application coordinates infrastructure checks, domain enforces rules
            if (!$this->stock->isAvailable($item['productId'], $item['quantity'])) {
                throw new \RuntimeException("Product '{$item['name']}' is out of stock.");
            }
            $order->addItem(new OrderItem(
                $item['productId'],
                $item['name'],
                $item['quantity'],
                new Money($item['unitPrice'])
            ));
        }

        $order->place(); // domain enforces: no empty orders, immutable once placed

        $txId = $this->payments->charge($cmd->customerId, $order->total());
        $this->orders->save($order);

        echo "[OrderApplicationService] PlaceOrder use case completed.\n";
        return new PlaceOrderResult($order->id, $order->total()->amount, $txId);
    }
}

// ============================================================
// PRESENTATION LAYER — HTTP controller
// ============================================================

class OrderController
{
    public function __construct(private OrderApplicationService $service) {}

    // Simulates handling POST /orders
    public function store(array $httpRequest): void
    {
        echo "\n[OrderController] POST /orders received\n";

        // Presentation only: map HTTP input → application command
        $result = $this->service->placeOrder(new PlaceOrderCommand(
            customerId: $httpRequest['customer_id'],
            items:      $httpRequest['items']
        ));

        // matiz: the controller formats the response — it never decides business outcomes.
        // Discount logic, order limits, fraud checks — those live in Domain or Application.
        echo "[OrderController] 201 Created — order {$result->orderId} | total \${$result->total} | tx: {$result->transactionId}\n";
    }
}

// ============================================================
// COMPOSITION ROOT — wire dependencies, execute
// ============================================================

$controller = new OrderController(
    new OrderApplicationService(
        new MySqlOrderRepository(),
        new StripePaymentGateway(),
        new WarehouseStockService()
    )
);

$controller->store([
    'customer_id' => 'cust_991',
    'items' => [
        ['productId' => 'prod_1', 'name' => 'Mechanical Keyboard', 'quantity' => 1, 'unitPrice' => 149.99],
        ['productId' => 'prod_2', 'name' => 'USB-C Hub',           'quantity' => 2, 'unitPrice' => 39.99],
    ],
]);
