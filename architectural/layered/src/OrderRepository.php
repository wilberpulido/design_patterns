<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment gateway, stock service
// ============================================================

interface OrderRepository
{
    public function save(Order $order): void;
}
