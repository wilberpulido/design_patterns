<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Observer;

// ─── Concrete Observers ───────────────────────────────────────────────────────
// Each observer reacts independently to the same event.
// Adding a new observer requires zero changes to OrderService.

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
