<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\CleanArchitecture;

// ─────────────────────────────────────────────
// INTERFACE ADAPTERS — third ring (Controllers, Presenters, Gateways)
// Convert data between use-case format and external formats (HTTP, DB)
// ─────────────────────────────────────────────

// Gateways (infrastructure adapters)
class InMemoryStudentGateway implements StudentGateway
{
    private array $store = [];

    public function seed(Student $s): void
    {
        $this->store[$s->getId()] = $s;
    }

    public function findById(string $id): ?Student
    {
        return $this->store[$id] ?? null;
    }

    public function save(Student $s): void
    {
        echo "[InMemoryStudentGateway] Saved student '{$s->getId()}'\n";
        $this->store[$s->getId()] = $s;
    }
}
