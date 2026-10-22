<?php

declare(strict_types=1);

/**
 * Laravel note:
 *
 * How Laravel applies Cache-Aside natively:
 * - `Cache::remember($key, $ttl, fn() => ...)` IS the Cache-Aside pattern built in:
 *   it looks up the key, returns it on a hit, and on a miss runs the closure,
 *   stores the result, and returns it. One line = the whole read path.
 * - `Cache::rememberForever($key, fn() => ...)` — same, without expiration.
 * - The write/invalidation side is manual on purpose: after updating a model you
 *   call `Cache::forget($key)` (typically inside an Observer or the model's `saved` event).
 *
 * Where it makes sense to apply Cache-Aside yourself in a Laravel project:
 * - Expensive aggregate queries (dashboards, reports) that don't change per request.
 * - Responses from slow third-party APIs (exchange rates, shipping quotes).
 * - Config/catalog data read on almost every request but written rarely.
 *   You still own the invalidation policy — Laravel only gives you the read shortcut.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/ProductDatabase.php        Source of truth — slow storage
//   src/InMemoryCache.php          Cache — stand-in for Redis/Memcached, with TTL
//   src/ProductRepository.php      Cache-aside logic: read → cache, miss → DB + populate;
//                                  write → DB + invalidate
// Executable guarantees of the pattern: tests/ProductRepositoryTest.php

require __DIR__.'/../../vendor/autoload.php';

use DesignPatterns\Cloud\CacheAside\InMemoryCache;
use DesignPatterns\Cloud\CacheAside\ProductDatabase;
use DesignPatterns\Cloud\CacheAside\ProductRepository;

// ─── Bootstrap ────────────────────────────────────────────────────────────────

echo "=== E-commerce Catalog — Cache-Aside Pattern ===\n\n";

$repo = new ProductRepository(new InMemoryCache, new ProductDatabase);

echo "--- Read #1: cold cache (expected MISS → DB → populate) ---\n";
$repo->getById(1);

echo "\n--- Read #2: warm cache (expected HIT, no DB) ---\n";
$repo->getById(1);

echo "\n--- Write: price change (DB update + cache invalidation) ---\n";
$repo->changePrice(1, 79.90);

echo "\n--- Read #3: after write (expected MISS → DB → fresh value) ---\n";
$product = $repo->getById(1);
echo "Current price: \${$product['price']}\n";

echo "\n--- Read #4: unknown product (MISS that hits DB and finds nothing) ---\n";
$repo->getById(999);
