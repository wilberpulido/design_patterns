<?php

/*
 * LARAVEL NOTE
 * ============
 * How Laravel uses the Strategy pattern:
 * Laravel applies Strategy extensively in its core through "drivers":
 *   - Filesystem: local, s3, ftp — each driver implements the same FilesystemAdapter contract
 *   - Cache: file, redis, database, memcached — same CacheStore interface
 *   - Mail: smtp, mailgun, ses — same Mailer interface
 *   - Queue: sync, database, redis, sqs — same Queue interface
 * Switching drivers is a one-line config change — the rest of the app is untouched.
 *
 * Where to apply it yourself in Laravel:
 * 1. Payment gateways: StripeStrategy, PaypalStrategy, MercadoPagoStrategy — all behind
 *    a PaymentGateway interface. Add a new provider without touching existing code.
 * 2. Notification channels: EmailNotification, SmsNotification, PushNotification.
 * 3. Pricing rules: RegularPricing, MemberPricing, PromoPricing — selected per user type.
 * 4. Export formats: CsvExporter, PdfExporter, XlsxExporter — same interface, different output.
 */

// The Strategy interface — all shipping methods must implement this contract.
interface ShippingStrategy
{
    public function calculateCost(float $weightKg, string $destination): float;
    public function estimatedDays(): int;
    public function label(): string;
}

// Concrete Strategy: express delivery — fast but expensive
class ExpressShipping implements ShippingStrategy
{
    public function calculateCost(float $weightKg, string $destination): float
    {
        echo "[ExpressShipping] Calculating express rate for {$weightKg}kg to {$destination}...\n";
        return 15.00 + ($weightKg * 2.50);
    }

    public function estimatedDays(): int { return 1; }
    public function label(): string { return 'Express (next day)'; }
}

// Concrete Strategy: standard delivery — balanced cost and speed
class StandardShipping implements ShippingStrategy
{
    public function calculateCost(float $weightKg, string $destination): float
    {
        echo "[StandardShipping] Calculating standard rate for {$weightKg}kg to {$destination}...\n";
        return 5.00 + ($weightKg * 0.80);
    }

    public function estimatedDays(): int { return 5; }
    public function label(): string { return 'Standard (3-5 days)'; }
}

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

// Client — selects a strategy based on user input and delegates to the calculator.
class OrderCheckout
{
    public function __construct(private ShippingCalculator $calculator) {}

    public function displayShippingOptions(string $product, float $weight, string $destination): void
    {
        echo "\n[OrderCheckout] Shipping options for \"{$product}\" ({$weight}kg → {$destination}):\n";

        $strategies = [
            new StandardShipping(),
            new ExpressShipping(),
            new InternationalShipping(),
        ];

        foreach ($strategies as $strategy) {
            $this->calculator->setStrategy($strategy);
            $quote = $this->calculator->quote($weight, $destination);
            echo "[OrderCheckout] {$quote['label']}: \${$quote['cost']} ({$quote['estimated_days']} days)\n";
        }
    }
}

// Bootstrap
$calculator = new ShippingCalculator(new StandardShipping());
$checkout   = new OrderCheckout($calculator);
$checkout->displayShippingOptions('Mechanical Keyboard', 1.2, 'Buenos Aires');
