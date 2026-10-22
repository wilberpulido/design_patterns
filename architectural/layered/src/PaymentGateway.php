<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment gateway, stock service
// ============================================================

interface PaymentGateway
{
    public function charge(string $customerId, Money $amount): string; // returns transaction ID
}
