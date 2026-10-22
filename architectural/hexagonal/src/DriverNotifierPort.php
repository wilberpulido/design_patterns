<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Hexagonal;

// ─────────────────────────────────────────────
// PORTS — interfaces owned by the application core (NOT by adapters)
//
// Primary ports  → how external actors DRIVE the application (use-case interfaces)
// Secondary ports → how the application DRIVES external systems (repository/notifier)
// ─────────────────────────────────────────────

interface DriverNotifierPort
{
    public function notifyAssignment(string $driverId, string $rideId): void;

    public function notifyRideStarted(string $passengerId, string $rideId): void;
}
