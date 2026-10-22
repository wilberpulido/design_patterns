<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\CacheAside;

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
