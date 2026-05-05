<?php

/*
 * LARAVEL NOTE:
 * Laravel uses the Observer pattern natively in two ways:
 *
 * 1. Eloquent Model Observers: php artisan make:observer OrderObserver --model=Order
 *    Laravel calls methods like created(), updated(), deleted() automatically
 *    when model events fire. Register with Order::observe(OrderObserver::class).
 *
 * 2. Events & Listeners: event(new OrderPlaced($order)) dispatches an event,
 *    and any registered listener reacts to it — decoupled and async-capable.
 *
 * Both mechanisms are classic Observer: the model/event is the Subject,
 * observers/listeners are the ConcreteObservers.
 */

// ─── Observer interface ───────────────────────────────────────────────────────
// Every observer must implement this contract.
// The subject only knows this interface — never the concrete classes.
interface OrderObserver
{
    public function onOrderPlaced(array $order): void;
}

// ─── Subject ──────────────────────────────────────────────────────────────────
// The subject manages a list of observers and notifies them on state change.
// It has no knowledge of what each observer does — that's the key decoupling.
class OrderService
{
    private array $observers = [];
    private array $placedOrders = [];

    public function attach(OrderObserver $observer): void
    {
        $this->observers[] = $observer;
        $className = get_class($observer);
        echo "[OrderService] Observer registered: {$className}\n";
    }

    public function detach(OrderObserver $observer): void
    {
        // matiz: detaching requires identity comparison, not value comparison.
        // In PHP, === on objects checks if they are the same instance.
        $this->observers = array_filter(
            $this->observers,
            fn($o) => $o !== $observer
        );
        $className = get_class($observer);
        echo "[OrderService] Observer removed: {$className}\n";
    }

    // The core of the pattern: notify all registered observers.
    // The subject broadcasts the event — it doesn't care who listens.
    private function notify(array $order): void
    {
        echo "[OrderService] Notifying " . count($this->observers) . " observer(s)...\n";
        foreach ($this->observers as $observer) {
            $observer->onOrderPlaced($order);
        }
    }

    public function placeOrder(string $product, int $qty, float $price): void
    {
        $order = [
            'id'      => uniqid('ORD-'),
            'product' => $product,
            'qty'     => $qty,
            'total'   => $qty * $price,
        ];

        $this->placedOrders[] = $order;

        echo "\n[OrderService] Order placed: #{$order['id']} — {$product} x{$qty} = \${$order['total']}\n";

        // State changed → notify all observers.
        $this->notify($order);
    }
}

// ─── Concrete Observers ───────────────────────────────────────────────────────
// Each observer reacts independently to the same event.
// Adding a new observer requires zero changes to OrderService.

class EmailConfirmationObserver implements OrderObserver
{
    public function onOrderPlaced(array $order): void
    {
        echo "[EmailConfirmation] Sending confirmation email for order #{$order['id']}...\n";
        echo "[EmailConfirmation] \"Your order of {$order['product']} is confirmed. Total: \${$order['total']}\"\n";
    }
}

class InventoryObserver implements OrderObserver
{
    private array $stock = ['Laptop' => 50, 'Monitor' => 30, 'Keyboard' => 100];

    public function onOrderPlaced(array $order): void
    {
        $product = $order['product'];
        $qty     = $order['qty'];

        if (isset($this->stock[$product])) {
            $this->stock[$product] -= $qty;
            echo "[Inventory] Stock updated: {$product} → {$this->stock[$product]} units remaining\n";
        } else {
            echo "[Inventory] Warning: product '{$product}' not found in inventory\n";
        }
    }
}

class FraudDetectionObserver implements OrderObserver
{
    private float $threshold = 500.0;

    public function onOrderPlaced(array $order): void
    {
        echo "[FraudDetection] Analyzing order #{$order['id']} (total: \${$order['total']})...\n";

        if ($order['total'] > $this->threshold) {
            echo "[FraudDetection] ⚠ HIGH VALUE ORDER — flagged for manual review\n";
        } else {
            echo "[FraudDetection] Order looks normal. No issues detected.\n";
        }
    }
}

// ─── Main ─────────────────────────────────────────────────────────────────────
echo "=== E-Commerce Order System — Observer Pattern ===\n\n";

$orderService = new OrderService();

$emailObserver = new EmailConfirmationObserver();
$inventoryObserver = new InventoryObserver();
$fraudObserver = new FraudDetectionObserver();

// Register observers — the subject is now aware of who wants to be notified.
$orderService->attach($emailObserver);
$orderService->attach($inventoryObserver);
$orderService->attach($fraudObserver);

// Place a normal order — all 3 observers react.
$orderService->placeOrder('Keyboard', 2, 49.99);

// Place a high-value order — fraud detection will flag it.
$orderService->placeOrder('Laptop', 3, 299.00);

// matiz: observers can be detached at runtime.
// This is useful for feature flags, maintenance windows, or A/B testing.
echo "\n--- Detaching FraudDetection observer ---\n";
$orderService->detach($fraudObserver);

// Now only email and inventory react.
$orderService->placeOrder('Monitor', 1, 199.00);
