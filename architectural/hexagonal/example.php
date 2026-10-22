<?php

declare(strict_types=1);

/**
 * LARAVEL NOTE
 *
 * HOW LARAVEL USES HEXAGONAL ARCHITECTURE:
 * - Service Providers are the Composition Root: $this->app->bind(RideRepositoryPort::class, EloquentRideRepository::class)
 *   wires secondary ports to adapters without the core ever knowing about Eloquent.
 * - Laravel Contracts (Illuminate\Contracts\Mail\Mailer, etc.) are first-party secondary ports;
 *   built-in drivers (SMTP, Mailgun, SES) are secondary adapters.
 * - Route controllers are primary adapters that translate HTTP into use-case calls.
 * - Laravel's IoC container handles the entire Composition Root automatically.
 *
 * WHERE TO APPLY IT YOURSELF IN LARAVEL:
 * - Define interfaces in App\Ports (e.g. RideRepositoryPort) and Eloquent implementations in App\Adapters\Persistence.
 * - Keep App\Domain free of Eloquent, Request, and any framework class.
 * - Swap EloquentRideRepository for InMemoryRideRepository in feature tests — no DB, no HTTP server needed.
 * - Use laravel-hexagonal-architecture or manually organise: Domain / Application / Adapters / Infrastructure.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   DOMAIN               Ride.php
//   PRIMARY PORTS        RequestRidePort.php, StartRidePort.php (driving: how the world calls the core)
//   SECONDARY PORTS      RideRepositoryPort.php, DriverNotifierPort.php (driven: what the core needs)
//   APPLICATION          RideMatchingService.php (implements primary, uses secondary ports)
//   PRIMARY ADAPTER      RideHttpController.php (HTTP → primary port)
//   SECONDARY ADAPTERS   InMemoryRideRepository.php, PushNotificationAdapter.php
// Executable guarantees of the pattern: tests/RideMatchingServiceTest.php

require __DIR__.'/../../vendor/autoload.php';

use DesignPatterns\Architectural\Hexagonal\InMemoryRideRepository;
use DesignPatterns\Architectural\Hexagonal\PushNotificationAdapter;
use DesignPatterns\Architectural\Hexagonal\RideHttpController;
use DesignPatterns\Architectural\Hexagonal\RideMatchingService;

// ─────────────────────────────────────────────
// COMPOSITION ROOT — the only place that knows about concrete classes
// In Laravel this lives in a Service Provider (register method)
// ─────────────────────────────────────────────

echo "=== Hexagonal Architecture — Ride-Hailing Service (PHP) ===\n\n";

$repository = new InMemoryRideRepository;
$notifier = new PushNotificationAdapter;
$service = new RideMatchingService($repository, $notifier);
$controller = new RideHttpController($service, $service);

echo "--- Scenario 1: Passenger requests a ride (driver available) ---\n";
$controller->postRide(['passenger_id' => 'passenger-42']);

echo "\n--- Scenario 2: Driver picks up the passenger ---\n";
$controller->patchStart('ride-1');

echo "\n--- Scenario 3: Second passenger requests (driver available) ---\n";
$controller->postRide(['passenger_id' => 'passenger-99']);
