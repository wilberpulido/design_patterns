<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Adapter;

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
