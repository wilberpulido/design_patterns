<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Cloud\CacheAside;

use DesignPatterns\Cloud\CacheAside\InMemoryCache;
use DesignPatterns\Cloud\CacheAside\ProductDatabase;
use DesignPatterns\Cloud\CacheAside\ProductRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductRepository::class)]
#[CoversClass(InMemoryCache::class)]
final class ProductRepositoryTest extends TestCase
{
    private ProductDatabase $db;

    private function repository(): ProductRepository
    {
        // Spy database: counts how many reads actually reach the "slow" storage.
        $this->db = new class extends ProductDatabase {
            public int $reads = 0;
            public function find(int $id): ?array { $this->reads++; return parent::find($id); }
        };

        return new ProductRepository(new InMemoryCache(), $this->db);
    }

    public function test_second_read_is_served_from_cache(): void
    {
        $this->expectOutputRegex('/from database.*from cache/s');
        $repository = $this->repository();

        $repository->getById(1);
        $repository->getById(1);

        $this->assertSame(1, $this->db->reads);
    }

    public function test_write_invalidates_the_cached_copy(): void
    {
        $this->expectOutputRegex('/\[Repository\]/');
        $repository = $this->repository();
        $repository->getById(1);                 // warm the cache

        $repository->changePrice(1, 79.90);
        $product = $repository->getById(1);      // must NOT return the stale cached price

        $this->assertEqualsWithDelta(79.90, $product['price'], 0.0001);
        $this->assertSame(2, $this->db->reads);
    }
}
