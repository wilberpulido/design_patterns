<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// DOMAIN LAYER — business rules, entities, no external deps
// ============================================================

class Order
{
    private array  $items  = [];
    private string $status = 'pending';

    public function __construct(
        public readonly string $id,
        public readonly string $customerId
    ) {}

    public function addItem(OrderItem $item): void
    {
        // Domain rule: placed orders are immutable
        if ($this->status !== 'pending') {
            throw new \LogicException("Cannot modify a {$this->status} order.");
        }
        $this->items[] = $item;
        echo "[Order] Added '{$item->name}' x{$item->quantity}\n";
    }

    public function place(): void
    {
        if (empty($this->items)) {
            throw new \LogicException("Cannot place an empty order.");
        }
        $this->status = 'placed';
        echo "[Order] Order {$this->id} placed — status: placed\n";
    }

    public function total(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            new Money(0)
        );
    }

    public function getStatus(): string { return $this->status; }
}
