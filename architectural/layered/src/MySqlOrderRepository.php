<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment gateway, stock service
// ============================================================

class MySqlOrderRepository implements OrderRepository
{
    public function save(Order $order): void
    {
        echo "[MySqlOrderRepository] INSERT/UPDATE order '{$order->id}' status='{$order->getStatus()}'\n";
    }
}
