<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment gateway, stock service
// ============================================================

interface StockService
{
    public function isAvailable(string $productId, int $quantity): bool;
}
