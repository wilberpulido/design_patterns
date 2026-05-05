<?php

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

// The target interface — what our application's payment module expects.
// All payment logic talks to this contract, not to any specific provider.
interface PaymentGateway
{
    public function charge(float $amount, string $currency, string $token): array;
    public function refund(string $transactionId): bool;
}

// The adaptee — a legacy third-party PayPal client we cannot modify.
// It has a completely different interface: different method names, different units,
// different return types. This is the incompatible class we need to adapt.
class LegacyPayPalClient
{
    // Expects amount in CENTS, not dollars — a common source of bugs without an adapter.
    public function submitPaymentRequest(int $amountInCents, string $currencyCode, string $cardToken, string $description): string
    {
        echo "[LegacyPayPalClient] Submitting {$amountInCents} cents ({$currencyCode}) | token: {$cardToken}\n";
        // Returns a raw PayPal transaction ID string.
        return 'PPL-TXN-' . strtoupper(substr(md5($cardToken . $amountInCents), 0, 8));
    }

    public function cancelTransaction(string $txnId, string $reason): array
    {
        echo "[LegacyPayPalClient] Cancelling transaction {$txnId} | reason: {$reason}\n";
        return ['status' => 'cancelled', 'txn_id' => $txnId];
    }
}

// The Adapter — wraps LegacyPayPalClient and implements PaymentGateway.
// This is an Object Adapter: it uses composition, not inheritance.
class PayPalAdapter implements PaymentGateway
{
    // matiz: Composition over inheritance — by holding a reference to the adaptee
    // instead of extending it, we can inject any LegacyPayPalClient (including mocks),
    // and we avoid inheriting methods or state we don't want exposed.
    private LegacyPayPalClient $paypal;

    // Maps our internal transaction IDs to PayPal's IDs, so refund() can look them up.
    private array $transactionMap = [];

    public function __construct(LegacyPayPalClient $paypal)
    {
        $this->paypal = $paypal;
    }

    public function charge(float $amount, string $currency, string $token): array
    {
        echo "[PayPalAdapter] charge(\${$amount}, '{$currency}') — translating to LegacyPayPalClient...\n";

        // Unit translation: our interface works in dollars, PayPal expects cents.
        $amountInCents = (int) round($amount * 100);

        $paypalTxnId = $this->paypal->submitPaymentRequest(
            $amountInCents,
            strtoupper($currency),
            $token,
            "Charge via adapter"
        );

        // Response normalization: translate PayPal's raw string into our standard array format.
        $internalTxnId = 'TXN-' . uniqid();
        $this->transactionMap[$internalTxnId] = $paypalTxnId;

        echo "[PayPalAdapter] Charge complete. Internal: {$internalTxnId} → PayPal: {$paypalTxnId}\n";

        return ['transaction_id' => $internalTxnId, 'status' => 'success', 'amount' => $amount];
    }

    public function refund(string $transactionId): bool
    {
        echo "[PayPalAdapter] refund('{$transactionId}') — translating to LegacyPayPalClient...\n";

        // Look up the PayPal transaction ID that corresponds to our internal ID.
        $paypalTxnId = $this->transactionMap[$transactionId] ?? $transactionId;
        $result = $this->paypal->cancelTransaction($paypalTxnId, "Customer refund request");

        return $result['status'] === 'cancelled';
    }
}

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
        echo "[OrderService] Refund " . ($refunded ? "successful" : "failed") . ".\n";
    }
}

// Wiring: inject the adapter wherever a PaymentGateway is expected.
// To switch to Stripe tomorrow, we only need a StripeAdapter — zero changes in OrderService.
$legacyPayPal = new LegacyPayPalClient();
$gateway = new PayPalAdapter($legacyPayPal);

processOrder($gateway, 99.99, 'USD', 'card_tok_4242424242');
