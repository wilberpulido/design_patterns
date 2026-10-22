<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Observer;

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
