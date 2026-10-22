<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Hexagonal;

// ─────────────────────────────────────────────
// PORTS — interfaces owned by the application core (NOT by adapters)
//
// Primary ports  → how external actors DRIVE the application (use-case interfaces)
// Secondary ports → how the application DRIVES external systems (repository/notifier)
// ─────────────────────────────────────────────

// Secondary ports — the core defines what it needs; adapters decide how to provide it
interface RideRepositoryPort
{
    public function nextId(): string;

    public function save(Ride $ride): void;

    public function findById(string $id): ?Ride;

    public function findAvailableDriverId(): ?string;
}
