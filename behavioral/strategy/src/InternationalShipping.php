<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Strategy;

// Concrete Strategy: international — customs and longer transit
class InternationalShipping implements ShippingStrategy
{
    public function calculateCost(float $weightKg, string $destination): float
    {
        echo "[InternationalShipping] Calculating international rate for {$weightKg}kg to {$destination}...\n";
        return 25.00 + ($weightKg * 4.00);
    }

    public function estimatedDays(): int { return 14; }
    public function label(): string { return 'International (10-14 days)'; }
}
