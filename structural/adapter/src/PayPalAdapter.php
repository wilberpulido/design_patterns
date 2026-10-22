<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Adapter;

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
