<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Adapter;

// The target interface — what our application's payment module expects.
// All payment logic talks to this contract, not to any specific provider.
interface PaymentGateway
{
    public function charge(float $amount, string $currency, string $token): array;
    public function refund(string $transactionId): bool;
}
