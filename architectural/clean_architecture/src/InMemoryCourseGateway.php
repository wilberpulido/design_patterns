<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\CleanArchitecture;

// ─────────────────────────────────────────────
// INTERFACE ADAPTERS — third ring (Controllers, Presenters, Gateways)
// Convert data between use-case format and external formats (HTTP, DB)
// ─────────────────────────────────────────────

class InMemoryCourseGateway implements CourseGateway
{
    private array $store = [];

    public function seed(Course $c): void
    {
        $this->store[$c->getId()] = $c;
    }

    public function findById(string $id): ?Course
    {
        return $this->store[$id] ?? null;
    }

    public function save(Course $c): void
    {
        $ratio = "{$c->getEnrollmentCount()}/{$c->getCapacity()}";
        echo "[InMemoryCourseGateway] Saved course '{$c->getId()}' — enrolled: {$ratio}\n";
        $this->store[$c->getId()] = $c;
    }
}
