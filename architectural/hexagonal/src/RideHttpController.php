<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Hexagonal;

// ─────────────────────────────────────────────
// PRIMARY ADAPTER — driving side, translates HTTP into use-case calls
// Knows about HTTP concepts; delegates all decisions to the ports
// ─────────────────────────────────────────────

class RideHttpController
{
    // matiz: depends on PRIMARY PORT interfaces, not the RideMatchingService class.
    // This controller can be tested by injecting a stub that implements RequestRidePort.
    public function __construct(
        private RequestRidePort $requestRide,
        private StartRidePort $startRide
    ) {}

    public function postRide(array $body): void
    {
        echo "[RideHttpController] POST /rides  passenger='{$body['passenger_id']}'\n";
        $result = $this->requestRide->request($body['passenger_id']);
        echo '[RideHttpController] 201 Created → '.json_encode($result)."\n";
    }

    public function patchStart(string $rideId): void
    {
        echo "[RideHttpController] PATCH /rides/{$rideId}/start\n";
        $this->startRide->start($rideId);
        echo "[RideHttpController] 200 OK → Ride started\n";
    }
}
