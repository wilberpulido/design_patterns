<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Repository;

// Domain object — a plain class with no ORM dependency
class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $category,
        public readonly float $price,
        public readonly bool $active
    ) {}
}
