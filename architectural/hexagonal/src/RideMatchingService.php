<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Hexagonal;

// ─────────────────────────────────────────────
// APPLICATION SERVICE — implements primary ports, depends only on secondary ports
// No HTTP, no ORM, no framework — pure orchestration
// ─────────────────────────────────────────────

class RideMatchingService implements RequestRidePort, StartRidePort
{
    // matiz: constructor injection of PORT interfaces (not concrete classes)
    // is what makes the core independently testable.
    // Swapping InMemoryRideRepository for PostgresRideRepository requires zero changes here.
    public function __construct(
        private RideRepositoryPort $rides,
        private DriverNotifierPort $notifier
    ) {}

    public function request(string $passengerId): array
    {
        $ride = new Ride($this->rides->nextId(), $passengerId);
        echo "[RideMatchingService] Ride '{$ride->getId()}' created for passenger '{$passengerId}'\n";

        $driverId = $this->rides->findAvailableDriverId();
        if ($driverId !== null) {
            $ride->accept($driverId);
            echo "[RideMatchingService] Auto-matched with driver '{$driverId}'\n";
            $this->notifier->notifyAssignment($driverId, $ride->getId());
        } else {
            echo "[RideMatchingService] No driver available — ride is queued\n";
        }

        $this->rides->save($ride);

        return [
            'ride_id'  => $ride->getId(),
            'status'   => $ride->getStatus(),
            'driver'   => $ride->getDriverId(),
        ];
    }

    public function start(string $rideId): void
    {
        $ride = $this->rides->findById($rideId)
            ?? throw new \InvalidArgumentException("Ride '{$rideId}' not found.");

        $ride->start();
        $this->rides->save($ride);
        $this->notifier->notifyRideStarted($ride->getPassengerId(), $rideId);
        echo "[RideMatchingService] Ride '{$rideId}' is now in progress\n";
    }
}
