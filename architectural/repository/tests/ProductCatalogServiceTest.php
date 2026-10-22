<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Architectural\Repository;

use DesignPatterns\Architectural\Repository\InMemoryProductRepository;
use DesignPatterns\Architectural\Repository\Product;
use DesignPatterns\Architectural\Repository\ProductCatalogService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryProductRepository::class)]
#[CoversClass(ProductCatalogService::class)]
final class ProductCatalogServiceTest extends TestCase
{
    public function test_in_memory_repository_honors_the_collection_contract(): void
    {
        $this->expectOutputRegex('/\[|^$/');
        $repository = new InMemoryProductRepository();
        $repository->save(new Product(901, 'Desk Lamp', 'office', 25.0, true));
        $repository->save(new Product(902, 'Old Lamp', 'office', 10.0, false));

        $this->assertSame('Desk Lamp', $repository->findById(901)?->name);
        $this->assertNull($repository->findById(999));
        $activeIds = array_map(fn(Product $p) => $p->id, $repository->findAllActive());
        $this->assertContains(901, $activeIds);
        $this->assertNotContains(902, $activeIds);
    }

    public function test_service_works_with_any_repository_implementation(): void
    {
        // The service only depends on the ProductRepository interface: swapping MySQL
        // for in-memory storage is what makes this test possible without a database.
        $this->expectOutputRegex('/Desk Lamp/');
        $repository = new InMemoryProductRepository();
        $repository->save(new Product(901, 'Desk Lamp', 'office', 25.0, true));

        (new ProductCatalogService($repository))->getProductDetails(901);
    }
}
