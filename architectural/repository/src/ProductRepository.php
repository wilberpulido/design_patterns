<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Repository;

// The Repository interface — defined in domain terms, not database terms.
// The service depends on this interface, never on a concrete implementation.
interface ProductRepository
{
    public function findById(int $id): ?Product;
    public function findByCategory(string $category): array;
    public function findAllActive(): array;
    public function save(Product $product): void;
}
