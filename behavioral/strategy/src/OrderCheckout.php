<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Strategy;

// Client — selects a strategy based on user input and delegates to the calculator.
class OrderCheckout
{
    public function __construct(private ShippingCalculator $calculator) {}

    public function displayShippingOptions(string $product, float $weight, string $destination): void
    {
        echo "\n[OrderCheckout] Shipping options for \"{$product}\" ({$weight}kg → {$destination}):\n";

        $strategies = [
            new StandardShipping,
            new ExpressShipping,
            new InternationalShipping,
        ];

        foreach ($strategies as $strategy) {
            $this->calculator->setStrategy($strategy);
            $quote = $this->calculator->quote($weight, $destination);
            echo "[OrderCheckout] {$quote['label']}: \${$quote['cost']} ({$quote['estimated_days']} days)\n";
        }
    }
}
