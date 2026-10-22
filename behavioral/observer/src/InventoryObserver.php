<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Observer;

// ─── Concrete Observers ───────────────────────────────────────────────────────
// Each observer reacts independently to the same event.
// Adding a new observer requires zero changes to OrderService.

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
