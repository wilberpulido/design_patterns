<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// DOMAIN LAYER — business rules, entities, no external deps
// ============================================================

class OrderItem
{
    public function __construct(
        public readonly string $productId,
        public readonly string $name,
        public readonly int $quantity,
        public readonly Money $unitPrice
    ) {}

    public function subtotal(): Money
    {
        return new Money($this->unitPrice->amount * $this->quantity, $this->unitPrice->currency);
    }
}
