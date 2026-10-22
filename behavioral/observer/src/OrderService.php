<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Observer;

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
        // matiz: inside a namespace, get_class() returns the fully qualified name
        // (DesignPatterns\Behavioral\Observer\EmailConfirmationObserver). getShortName()
        // keeps the log readable without hardcoding the namespace anywhere.
        $className = (new \ReflectionClass($observer))->getShortName();
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
        $className = (new \ReflectionClass($observer))->getShortName();
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
