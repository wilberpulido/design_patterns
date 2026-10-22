<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Hexagonal;

// ─────────────────────────────────────────────
// DOMAIN — pure business objects, zero framework dependencies
// ─────────────────────────────────────────────

class Ride
{
    private ?string $driverId = null;
    private string  $status   = 'requested'; // requested → accepted → in_progress → completed

    public function __construct(
        private readonly string $id,
        private readonly string $passengerId
    ) {}

    // Domain invariant: only a 'requested' ride may be accepted
    public function accept(string $driverId): void
    {
        if ($this->status !== 'requested') {
            throw new \DomainException("Ride '{$this->id}' cannot be accepted — status is '{$this->status}'.");
        }
        $this->driverId = $driverId;
        $this->status   = 'accepted';
    }

    public function start(): void
    {
        if ($this->status !== 'accepted') {
            throw new \DomainException("Ride '{$this->id}' must be accepted before starting.");
        }
        $this->status = 'in_progress';
    }

    public function complete(): void
    {
        if ($this->status !== 'in_progress') {
            throw new \DomainException("Ride '{$this->id}' must be in progress before completing.");
        }
        $this->status = 'completed';
    }

    public function getId(): string        { return $this->id; }
    public function getPassengerId(): string { return $this->passengerId; }
    public function getDriverId(): ?string   { return $this->driverId; }
    public function getStatus(): string      { return $this->status; }
}
