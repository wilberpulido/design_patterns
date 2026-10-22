<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Hexagonal;

// ─────────────────────────────────────────────
// SECONDARY ADAPTERS — driven side, implement secondary ports
// These know about the real infrastructure; the core does not
// ─────────────────────────────────────────────

class PushNotificationAdapter implements DriverNotifierPort
{
    public function notifyAssignment(string $driverId, string $rideId): void
    {
        echo "[PushNotificationAdapter] Push → driver '{$driverId}': New ride '{$rideId}' assigned\n";
    }

    public function notifyRideStarted(string $passengerId, string $rideId): void
    {
        echo "[PushNotificationAdapter] Push → passenger '{$passengerId}': Your ride '{$rideId}' has started\n";
    }
}
