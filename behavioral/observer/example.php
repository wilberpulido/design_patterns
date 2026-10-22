<?php

declare(strict_types=1);

/*
 * LARAVEL NOTE:
 * Laravel uses the Observer pattern natively in two ways:
 *
 * 1. Eloquent Model Observers: php artisan make:observer OrderObserver --model=Order
 *    Laravel calls methods like created(), updated(), deleted() automatically
 *    when model events fire. Register with Order::observe(OrderObserver::class).
 *
 * 2. Events & Listeners: event(new OrderPlaced($order)) dispatches an event,
 *    and any registered listener reacts to it — decoupled and async-capable.
 *
 * Both mechanisms are classic Observer: the model/event is the Subject,
 * observers/listeners are the ConcreteObservers.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/OrderObserver.php          Observer — the interface every listener implements
//   src/OrderService.php           Subject — keeps the observer list: attach(), detach(), notify
//   src/EmailConfirmationObserver.php Concrete observers — independent reactions to the same event
//   src/InventoryObserver.php
//   src/FraudDetectionObserver.php
// Executable guarantees of the pattern: tests/OrderServiceTest.php

require __DIR__ . '/../../vendor/autoload.php';

use DesignPatterns\Behavioral\Observer\EmailConfirmationObserver;
use DesignPatterns\Behavioral\Observer\FraudDetectionObserver;
use DesignPatterns\Behavioral\Observer\InventoryObserver;
use DesignPatterns\Behavioral\Observer\OrderService;

// ─── Main ─────────────────────────────────────────────────────────────────────

echo "=== E-Commerce Order System — Observer Pattern ===\n\n";

$orderService = new OrderService();

$emailObserver = new EmailConfirmationObserver();
$inventoryObserver = new InventoryObserver();
$fraudObserver = new FraudDetectionObserver();

// Register observers — the subject is now aware of who wants to be notified.
$orderService->attach($emailObserver);
$orderService->attach($inventoryObserver);
$orderService->attach($fraudObserver);

// Place a normal order — all 3 observers react.
$orderService->placeOrder('Keyboard', 2, 49.99);

// Place a high-value order — fraud detection will flag it.
$orderService->placeOrder('Laptop', 3, 299.00);

// matiz: observers can be detached at runtime.
// This is useful for feature flags, maintenance windows, or A/B testing.
echo "\n--- Detaching FraudDetection observer ---\n";
$orderService->detach($fraudObserver);

// Now only email and inventory react.
$orderService->placeOrder('Monitor', 1, 199.00);
