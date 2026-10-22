<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\Retry;

// ─── Payment Gateway ──────────────────────────────────────────────────────────
// Simulates a flaky payment provider that times out under load.

class StripeGateway
{
    private int $remainingFailures;

    public function __construct(int $simulatedFailures = 0)
    {
        $this->remainingFailures = $simulatedFailures;
    }

    public function charge(string $cardToken, float $amount): string
    {
        echo "[StripeGateway] Charging \${$amount} on token {$cardToken}...\n";

        if ($this->remainingFailures > 0) {
            $this->remainingFailures--;
            throw new TransientException('Connection timeout to Stripe API');
        }

        $txnId = 'txn_'.strtoupper(substr(md5($cardToken.microtime()), 0, 10));
        echo "[StripeGateway] Charge approved. Transaction ID: {$txnId}\n";

        return $txnId;
    }

    public function chargeDeclinedCard(string $cardToken, float $amount): string
    {
        echo "[StripeGateway] Charging \${$amount} on token {$cardToken}...\n";
        throw new PermanentException('Card declined: insufficient funds (do not retry)');
    }
}
