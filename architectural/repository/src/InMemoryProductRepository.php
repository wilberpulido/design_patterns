<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Repository;

// matiz: the InMemory implementation makes the service fully testable without a database.
// In tests you inject InMemoryProductRepository — no migrations, no seeders, instant feedback.
// This is the most immediate practical benefit of the Repository pattern.
class InMemoryProductRepository implements ProductRepository
{
    private array $products = [];

    public function findById(int $id): ?Product
    {
        echo "[InMemoryProductRepository] Looking up product #{$id} in memory...\n";
        return $this->products[$id] ?? null;
    }

    public function findByCategory(string $category): array
    {
        echo "[InMemoryProductRepository] Filtering products by category '{$category}' in memory...\n";
        return array_values(array_filter(
            $this->products,
            fn(Product $p) => $p->category === $category && $p->active
        ));
    }

    public function findAllActive(): array
    {
        return array_values(array_filter($this->products, fn(Product $p) => $p->active));
    }

    public function save(Product $product): void
    {
        $this->products[$product->id] = $product;
        echo "[InMemoryProductRepository] Saved '{$product->name}' in memory store.\n";
    }
}
