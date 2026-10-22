<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Strategy;

// The Strategy interface — all shipping methods must implement this contract.
interface ShippingStrategy
{
    public function calculateCost(float $weightKg, string $destination): float;

    public function estimatedDays(): int;

    public function label(): string;
}
