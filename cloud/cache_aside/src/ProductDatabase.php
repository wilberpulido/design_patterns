<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\CacheAside;

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
