<?php

declare(strict_types=1);

namespace DesignPatterns\Behavioral\Observer;

// ─── Observer interface ───────────────────────────────────────────────────────
// Every observer must implement this contract.
// The subject only knows this interface — never the concrete classes.
interface OrderObserver
{
    public function onOrderPlaced(array $order): void;
}
