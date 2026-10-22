<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// APPLICATION LAYER — use cases, DTOs, orchestration
// ============================================================

class PlaceOrderResult
{
    public function __construct(
        public readonly string $orderId,
        public readonly float $total,
        public readonly string $transactionId
    ) {}
}
