<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// DOMAIN LAYER — business rules, entities, no external deps
// ============================================================

class Money
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency = 'USD'
    ) {}

    public function add(Money $other): Money
    {
        return new Money($this->amount + $other->amount, $this->currency);
    }
}
