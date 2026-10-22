<?php

declare(strict_types=1);

/**
 * LARAVEL NOTE:
 *
 * Laravel uses this pattern natively in:
 * - Filesystem (Storage facade): adapts local disk, S3, FTP behind a single interface.
 * - Cache system: adapts Redis, Memcached, file, database drivers to a unified Cache interface.
 * - Mail: adapts SMTP, Mailgun, SES, Postmark via Symfony Mailer transport adapters.
 * - Broadcasting: adapts Pusher, Ably, Redis to a common Broadcaster interface.
 * - Queue: adapts SQS, Redis, database, Beanstalkd behind a unified Queue interface.
 *
 * When to apply it yourself in Laravel:
 * - Wrapping a third-party payment SDK (Stripe, PayPal, Mercado Pago) behind your own
 *   PaymentGateway interface, so you can swap providers without touching business logic.
 * - Adapting a legacy internal microservice or external REST API to a clean local interface,
 *   keeping your domain free of vendor-specific details.
 * - Wrapping SMS or push notification providers (Twilio, Firebase) behind a unified
 *   NotificationChannel interface to make them interchangeable.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/PaymentGateway.php         Target — the interface the client code expects
//   src/LegacyPayPalClient.php     Adaptee — existing class with an incompatible API (cents, codes)
//   src/PayPalAdapter.php          Adapter — implements the Target by translating to the Adaptee
//   (below) processOrder()         Client — only knows PaymentGateway
// Executable guarantees of the pattern: tests/PayPalAdapterTest.php

require __DIR__.'/../../vendor/autoload.php';

use DesignPatterns\Structural\Adapter\LegacyPayPalClient;
use DesignPatterns\Structural\Adapter\PaymentGateway;
use DesignPatterns\Structural\Adapter\PayPalAdapter;

// Client code — only knows about PaymentGateway.
// It has zero knowledge of PayPal, cents conversion, or callback formats.
// This is the key benefit: business logic is decoupled from the payment provider.

function processOrder(PaymentGateway $gateway, float $amount, string $currency, string $token): void
{
    echo "\n[OrderService] Processing order — \${$amount} {$currency}...\n";
    $result = $gateway->charge($amount, $currency, $token);

    if ($result['status'] === 'success') {
        echo "[OrderService] Order confirmed. Transaction ID: {$result['transaction_id']}\n";

        echo "\n[OrderService] Customer requested refund for {$result['transaction_id']}...\n";
        $refunded = $gateway->refund($result['transaction_id']);
        echo '[OrderService] Refund '.($refunded ? 'successful' : 'failed').".\n";
    }
}

// Wiring: inject the adapter wherever a PaymentGateway is expected.
// To switch to Stripe tomorrow, we only need a StripeAdapter — zero changes in OrderService.
$legacyPayPal = new LegacyPayPalClient;
$gateway = new PayPalAdapter($legacyPayPal);

processOrder($gateway, 99.99, 'USD', 'card_tok_4242424242');
