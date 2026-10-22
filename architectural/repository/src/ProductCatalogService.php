<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Repository;

// The Service — uses the repository interface exclusively.
// It has no idea whether data comes from MySQL, MongoDB, or memory.
class ProductCatalogService
{
    public function __construct(private ProductRepository $repository) {}

    public function getProductDetails(int $id): void
    {
        echo "\n[ProductCatalogService] Fetching product #{$id}...\n";
        $product = $this->repository->findById($id);
        if ($product) {
            echo "[ProductCatalogService] Found: {$product->name} | {$product->category} | \${$product->price}\n";
        } else {
            echo "[ProductCatalogService] Product not found.\n";
        }
    }

    public function listByCategory(string $category): void
    {
        echo "\n[ProductCatalogService] Listing products in '{$category}'...\n";
        foreach ($this->repository->findByCategory($category) as $product) {
            echo "[ProductCatalogService]   - {$product->name} (\${$product->price})\n";
        }
    }
}
