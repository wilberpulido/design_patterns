<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

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
