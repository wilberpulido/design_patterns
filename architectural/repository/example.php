<?php

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

// Domain object — a plain class with no ORM dependency
class Product
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $category,
        public readonly float  $price,
        public readonly bool   $active
    ) {}
}

// The Repository interface — defined in domain terms, not database terms.
// The service depends on this interface, never on a concrete implementation.
interface ProductRepository
{
    public function findById(int $id): ?Product;
    public function findByCategory(string $category): array;
    public function findAllActive(): array;
    public function save(Product $product): void;
}

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
            new Product(2, 'USB Mouse',          $category, 29.99, true),
        ];
    }

    public function findAllActive(): array
    {
        echo "[MySqlProductRepository] SELECT * FROM products WHERE active = 1\n";
        return [
            new Product(1, 'Wireless Keyboard', 'electronics', 79.99, true),
            new Product(2, 'USB Mouse',          'electronics', 29.99, true),
            new Product(3, 'Notebook',           'stationery',   4.99, true),
        ];
    }

    public function save(Product $product): void
    {
        echo "[MySqlProductRepository] INSERT/UPDATE product '{$product->name}' in database\n";
    }
}

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
