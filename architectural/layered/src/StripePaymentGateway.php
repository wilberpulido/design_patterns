<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment gateway, stock service
// ============================================================

class StripePaymentGateway implements PaymentGateway
{
    public function charge(string $customerId, Money $amount): string
    {
        echo "[StripePaymentGateway] Charging customer '{$customerId}' \${$amount->amount} via Stripe...\n";

        return 'txn_'.substr(md5(uniqid()), 0, 8);
    }
}
