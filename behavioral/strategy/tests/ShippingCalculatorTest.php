<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Behavioral\Strategy;

use DesignPatterns\Behavioral\Strategy\ExpressShipping;
use DesignPatterns\Behavioral\Strategy\ShippingCalculator;
use DesignPatterns\Behavioral\Strategy\ShippingStrategy;
use DesignPatterns\Behavioral\Strategy\StandardShipping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingCalculator::class)]
#[CoversClass(StandardShipping::class)]
#[CoversClass(ExpressShipping::class)]
final class ShippingCalculatorTest extends TestCase
{
    public function test_quote_delegates_to_the_injected_strategy(): void
    {
        // A hand-made strategy proves the Context depends only on the interface:
        // it has never heard of this class, yet it works without any change.
        $flatRate = new class implements ShippingStrategy {
            public function calculateCost(float $weightKg, string $destination): float { return 9.99; }
            public function estimatedDays(): int { return 2; }
            public function label(): string { return 'Flat rate'; }
        };

        $quote = (new ShippingCalculator($flatRate))->quote(3.0, 'Córdoba');

        $this->assertSame(
            ['label' => 'Flat rate', 'cost' => 9.99, 'estimated_days' => 2],
            $quote
        );
    }

    public function test_strategy_can_be_swapped_at_runtime(): void
    {
        $this->expectOutputRegex('/Switching strategy to: Express/');

        $calculator = new ShippingCalculator(new StandardShipping());
        $standard = $calculator->quote(2.0, 'Rosario');

        // Same Context object, different behavior after the swap.
        $calculator->setStrategy(new ExpressShipping());
        $express = $calculator->quote(2.0, 'Rosario');

        // matiz: money as float — compare with a delta, never with ===.
        // 0.80 has no exact binary representation, so 5.00 + 2.0 * 0.80 may be 6.6000000000000005.
        $this->assertEqualsWithDelta(6.6, $standard['cost'], 0.0001);   // 5.00 + 2.0 * 0.80
        $this->assertEqualsWithDelta(20.0, $express['cost'], 0.0001);   // 15.00 + 2.0 * 2.50
        $this->assertSame(1, $express['estimated_days']);
    }
}
