<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Hexagonal;

// ─────────────────────────────────────────────
// SECONDARY ADAPTERS — driven side, implement secondary ports
// These know about the real infrastructure; the core does not
// ─────────────────────────────────────────────

class InMemoryRideRepository implements RideRepositoryPort
{
    private array $store     = [];
    private array $drivers   = ['driver-001', 'driver-002', 'driver-003'];
    private int   $seq       = 1;

    public function nextId(): string { return 'ride-' . $this->seq++; }

    public function save(Ride $ride): void
    {
        echo "[InMemoryRideRepository] Stored ride '{$ride->getId()}' — status: {$ride->getStatus()}\n";
        $this->store[$ride->getId()] = $ride;
    }

    public function findById(string $id): ?Ride
    {
        return $this->store[$id] ?? null;
    }

    public function findAvailableDriverId(): ?string
    {
        // matiz: in production this would call a geolocation service with proximity algorithms;
        // here we pop from a list to keep the focus on the hexagonal boundary, not the algorithm.
        return array_shift($this->drivers) ?: null;
    }
}
