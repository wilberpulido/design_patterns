<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Strategy;

// The Context — uses a ShippingStrategy without knowing which one it is.
// It delegates the cost calculation entirely to the injected strategy.
class ShippingCalculator
{
    public function __construct(private ShippingStrategy $strategy) {}

    // matiz: the strategy can be swapped at runtime, not just at construction.
    // This allows the same context object to behave differently as conditions change —
    // for example, switching from standard to express if a product runs low on stock.
    public function setStrategy(ShippingStrategy $strategy): void
    {
        echo "[ShippingCalculator] Switching strategy to: {$strategy->label()}\n";
        $this->strategy = $strategy;
    }

    public function quote(float $weightKg, string $destination): array
    {
        $cost = $this->strategy->calculateCost($weightKg, $destination);
        return [
            'label'          => $this->strategy->label(),
            'cost'           => $cost,
            'estimated_days' => $this->strategy->estimatedDays(),
        ];
    }
}
