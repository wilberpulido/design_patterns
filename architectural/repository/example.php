<?php

declare(strict_types=1);

/*
 * LARAVEL NOTE
 * ============
 * How Laravel relates to the Repository pattern:
 * Laravel's Eloquent uses Active Record — the model IS both the domain object
 * AND the query builder. This is convenient but couples business logic to the ORM.
 * Eloquent is NOT a Repository — it's a different pattern that trades separation for simplicity.
 *
 * Where to apply Repository yourself in Laravel:
 * 1. When you want testable services: inject an InMemoryProductRepository in tests
 *    instead of hitting a real database. No migrations, no seeders, instant feedback.
 * 2. When you want to centralize query logic: instead of the same Eloquent query
 *    duplicated in 5 controllers, it lives once in ProductRepository::findByCategory().
 * 3. When building Hexagonal or Clean Architecture: the repository is the "output port"
 *    that isolates the domain from the persistence layer.
 * 4. When you might swap data sources: MySQL today, Elasticsearch tomorrow — only the
 *    repository implementation changes, the service is untouched.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/Product.php                Domain entity
//   src/ProductRepository.php      Repository interface — collection-like contract, no SQL
//   src/MySqlProductRepository.php Implementations — swappable storage behind the same contract
//   src/InMemoryProductRepository.php
//   src/ProductCatalogService.php  Client — depends only on the interface
// Executable guarantees of the pattern: tests/ProductCatalogServiceTest.php

require __DIR__ . '/../../vendor/autoload.php';

use DesignPatterns\Architectural\Repository\InMemoryProductRepository;
use DesignPatterns\Architectural\Repository\MySqlProductRepository;
use DesignPatterns\Architectural\Repository\Product;
use DesignPatterns\Architectural\Repository\ProductCatalogService;

// Production: inject MySQL implementation

echo "=== Production (MySQL) ===\n";
$service = new ProductCatalogService(new MySqlProductRepository());
$service->getProductDetails(1);
$service->listByCategory('electronics');

// Testing: swap to InMemory — zero database dependency
echo "\n=== Tests (InMemory) ===\n";
$repo = new InMemoryProductRepository();
$repo->save(new Product(1, 'Test Keyboard', 'electronics', 49.99, true));
$repo->save(new Product(2, 'Test Mouse',    'electronics', 19.99, true));
$service = new ProductCatalogService($repo);
$service->listByCategory('electronics');
$service->getProductDetails(99); // not found
