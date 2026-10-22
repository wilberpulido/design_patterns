<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Strategy;

// Concrete Strategy: express delivery — fast but expensive
class ExpressShipping implements ShippingStrategy
{
    public function calculateCost(float $weightKg, string $destination): float
    {
        echo "[ExpressShipping] Calculating express rate for {$weightKg}kg to {$destination}...\n";

        return 15.00 + ($weightKg * 2.50);
    }

    public function estimatedDays(): int
    {
        return 1;
    }

    public function label(): string
    {
        return 'Express (next day)';
    }
}
