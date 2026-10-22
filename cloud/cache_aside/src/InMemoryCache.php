<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\CacheAside;

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
