<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Strategy;

// Concrete Strategy: standard delivery — balanced cost and speed
class StandardShipping implements ShippingStrategy
{
    public function calculateCost(float $weightKg, string $destination): float
    {
        echo "[StandardShipping] Calculating standard rate for {$weightKg}kg to {$destination}...\n";

        return 5.00 + ($weightKg * 0.80);
    }

    public function estimatedDays(): int
    {
        return 5;
    }

    public function label(): string
    {
        return 'Standard (3-5 days)';
    }
}
