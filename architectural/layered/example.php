<?php

declare(strict_types=1);

/*
 * Laravel:
 * 1. Laravel naturally maps to Layered Architecture out of the box:
 *    - Presentation → Controllers, Form Requests, Resources (API responses)
 *    - Application  → Service classes (not built-in, but the conventional place)
 *    - Domain       → Eloquent Models with business methods, Value Objects
 *    - Infrastructure → Eloquent queries, Mail, Queue, Storage facades
 *    The framework doesn't enforce strict layering, but nothing prevents it.
 *
 * 2. Apply Layered Architecture yourself in Laravel when:
 *    - A controller exceeds routing concerns — extract an Application Service class
 *    - Business rules are scattered across controllers, observers, and models
 *    - You want to unit-test domain logic without HTTP, DB, or mail setup
 *    - You're building a domain-rich app where Eloquent Active Record is insufficient
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   PRESENTATION         OrderController.php
//   APPLICATION          OrderApplicationService.php, PlaceOrderCommand.php, PlaceOrderResult.php
//   DOMAIN               Order.php, OrderItem.php, Money.php
//   INFRASTRUCTURE       contracts: OrderRepository.php, PaymentGateway.php, StockService.php
//                        implementations: MySqlOrderRepository.php, StripePaymentGateway.php,
//                        WarehouseStockService.php
// Executable guarantees of the pattern: tests/OrderApplicationServiceTest.php

require __DIR__.'/../../vendor/autoload.php';

use DesignPatterns\Architectural\Layered\MySqlOrderRepository;
use DesignPatterns\Architectural\Layered\OrderApplicationService;
use DesignPatterns\Architectural\Layered\OrderController;
use DesignPatterns\Architectural\Layered\StripePaymentGateway;
use DesignPatterns\Architectural\Layered\WarehouseStockService;

// ============================================================
// COMPOSITION ROOT — wire dependencies, execute
// ============================================================

$controller = new OrderController(
    new OrderApplicationService(
        new MySqlOrderRepository,
        new StripePaymentGateway,
        new WarehouseStockService
    )
);

$controller->store([
    'customer_id' => 'cust_991',
    'items' => [
        ['productId' => 'prod_1', 'name' => 'Mechanical Keyboard', 'quantity' => 1, 'unitPrice' => 149.99],
        ['productId' => 'prod_2', 'name' => 'USB-C Hub',           'quantity' => 2, 'unitPrice' => 39.99],
    ],
]);
