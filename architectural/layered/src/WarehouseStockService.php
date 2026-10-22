<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment gateway, stock service
// ============================================================

class WarehouseStockService implements StockService
{
    public function isAvailable(string $productId, int $quantity): bool
    {
        echo "[WarehouseStockService] Checking stock for '{$productId}' (qty: {$quantity})...\n";

        return true;
    }
}
