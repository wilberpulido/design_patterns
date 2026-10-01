<?php

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

// ─── Cache store ──────────────────────────────────────────────────────────────
// Stands in for Redis/Memcached. The point of the pattern is that this store is
// FAST and VOLATILE: losing it must never lose data, because the database is the
// source of truth. The cache only holds copies.

class InMemoryCache
{
    /** @var array<string, array{value: mixed, expiresAt: float}> */
    private array $store = [];

    public function get(string $key): mixed
    {
        if (!isset($this->store[$key])) {
            echo "[Cache] MISS for '{$key}' (not present)\n";
            return null;
        }

        // matiz: TTL expiration. An entry that is present but expired is treated as a MISS.
        // This is how the cache self-heals stale data: it bounds how wrong a copy can be.
        if (microtime(true) > $this->store[$key]['expiresAt']) {
            echo "[Cache] MISS for '{$key}' (expired)\n";
            unset($this->store[$key]);
            return null;
        }

        echo "[Cache] HIT for '{$key}'\n";
        return $this->store[$key]['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        echo "[Cache] SET '{$key}' (ttl={$ttlSeconds}s)\n";
        $this->store[$key] = [
            'value'     => $value,
            'expiresAt' => microtime(true) + $ttlSeconds,
        ];
    }

    public function forget(string $key): void
    {
        echo "[Cache] FORGET '{$key}'\n";
        unset($this->store[$key]);
    }
}

// ─── Source of truth ──────────────────────────────────────────────────────────
// The slow, authoritative store. Every read here is "expensive" — that cost is
// exactly what Cache-Aside is trying to avoid paying twice.

class ProductDatabase
{
    /** @var array<int, array{id:int, name:string, price:float}> */
    private array $rows = [
        1 => ['id' => 1, 'name' => 'Mechanical Keyboard', 'price' => 89.90],
        2 => ['id' => 2, 'name' => 'Noise-Cancelling Headset', 'price' => 149.00],
    ];

    public function find(int $id): ?array
    {
        echo "[Database] SELECT product #{$id} (slow query ~200ms)...\n";
        usleep(200_000); // simulate real query latency
        return $this->rows[$id] ?? null;
    }

    public function updatePrice(int $id, float $price): void
    {
        echo "[Database] UPDATE product #{$id} price = \${$price}\n";
        $this->rows[$id]['price'] = $price;
    }
}

// ─── Repository (the Cache-Aside logic lives here) ────────────────────────────
// Scenario: an e-commerce product catalog. Products are read on nearly every page
// view but change rarely, so they are the textbook case for caching.
//
// The repository — NOT the cache and NOT the database — owns the aside logic:
// "look aside" to the cache first, fall back to the DB, and populate the cache.

class ProductRepository
{
    private const TTL = 30;

    public function __construct(
        private InMemoryCache   $cache,
        private ProductDatabase $db
    ) {}

    public function getById(int $id): ?array
    {
        $key = "product:{$id}";

        // 1) Read path: ask the cache first.
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            echo "[Repository] Served product #{$id} from cache.\n";
            return $cached;
        }

        // 2) On a miss, load from the source of truth...
        $product = $this->db->find($id);
        if ($product === null) {
            // matiz: negative caching. Without it, requests for a non-existent id
            // hit the DB every single time (a "cache penetration" attack vector).
            // Here we simply don't cache the miss to keep the example focused, but a
            // production system would cache a short-lived tombstone for ~seconds.
            echo "[Repository] Product #{$id} does not exist.\n";
            return null;
        }

        // 3) ...then populate the cache so the next read is a hit.
        $this->cache->set($key, $product, self::TTL);
        echo "[Repository] Served product #{$id} from database (now cached).\n";
        return $product;
    }

    public function changePrice(int $id, float $price): void
    {
        // Write path. Cache-Aside writes go to the DB first — the source of truth
        // must always win — and then INVALIDATE the cached copy.
        $this->db->updatePrice($id, $price);

        // matiz: invalidate, don't update-in-place. Writing the new value into the
        // cache here ("write-through") is tempting, but under concurrency it can
        // resurrect stale data (two writers racing). Deleting the key is safe: the
        // next reader simply repopulates from the fresh DB state.
        $this->cache->forget("product:{$id}");
    }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

echo "=== E-commerce Catalog — Cache-Aside Pattern ===\n\n";

$repo = new ProductRepository(new InMemoryCache(), new ProductDatabase());

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
