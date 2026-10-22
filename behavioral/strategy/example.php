<?php

declare(strict_types=1);

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

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/ShippingStrategy.php       Strategy — the interface every algorithm implements
//   src/StandardShipping.php       Concrete strategies — interchangeable algorithms
//   src/ExpressShipping.php
//   src/InternationalShipping.php
//   src/ShippingCalculator.php     Context — delegates to whichever strategy it holds;
//                                  setStrategy() swaps it at runtime
//   src/OrderCheckout.php          Client — chooses strategies and uses the context
// Executable guarantees of the pattern: tests/ShippingCalculatorTest.php

require __DIR__ . '/../../vendor/autoload.php';

use DesignPatterns\Behavioral\Strategy\OrderCheckout;
use DesignPatterns\Behavioral\Strategy\ShippingCalculator;
use DesignPatterns\Behavioral\Strategy\StandardShipping;

// Bootstrap
$calculator = new ShippingCalculator(new StandardShipping());
$checkout   = new OrderCheckout($calculator);
$checkout->displayShippingOptions('Mechanical Keyboard', 1.2, 'Buenos Aires');
