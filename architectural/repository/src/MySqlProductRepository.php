<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Repository;

// Concrete implementation: MySQL — simulates Eloquent or raw queries
class MySqlProductRepository implements ProductRepository
{
    public function findById(int $id): ?Product
    {
        echo "[MySqlProductRepository] SELECT * FROM products WHERE id = {$id}\n";

        return new Product($id, 'Wireless Keyboard', 'electronics', 79.99, true);
    }

    public function findByCategory(string $category): array
    {
        echo "[MySqlProductRepository] SELECT * FROM products WHERE category = '{$category}' AND active = 1\n";

        return [
            new Product(1, 'Wireless Keyboard', $category, 79.99, true),
            new Product(2, 'USB Mouse', $category, 29.99, true),
        ];
    }

    public function findAllActive(): array
    {
        echo "[MySqlProductRepository] SELECT * FROM products WHERE active = 1\n";

        return [
            new Product(1, 'Wireless Keyboard', 'electronics', 79.99, true),
            new Product(2, 'USB Mouse', 'electronics', 29.99, true),
            new Product(3, 'Notebook', 'stationery', 4.99, true),
        ];
    }

    public function save(Product $product): void
    {
        echo "[MySqlProductRepository] INSERT/UPDATE product '{$product->name}' in database\n";
    }
}
