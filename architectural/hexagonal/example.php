<?php

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

// ─────────────────────────────────────────────
// PORTS — interfaces owned by the application core (NOT by adapters)
//
// Primary ports  → how external actors DRIVE the application (use-case interfaces)
// Secondary ports → how the application DRIVES external systems (repository/notifier)
// ─────────────────────────────────────────────

// Primary ports
interface RequestRidePort
{
    public function request(string $passengerId): array;
}

interface StartRidePort
{
    public function start(string $rideId): void;
}

// Secondary ports — the core defines what it needs; adapters decide how to provide it
interface RideRepositoryPort
{
    public function nextId(): string;
    public function save(Ride $ride): void;
    public function findById(string $id): ?Ride;
    public function findAvailableDriverId(): ?string;
}

interface DriverNotifierPort
{
    public function notifyAssignment(string $driverId, string $rideId): void;
    public function notifyRideStarted(string $passengerId, string $rideId): void;
}

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
        private StartRidePort   $startRide
    ) {}

    public function postRide(array $body): void
    {
        echo "[RideHttpController] POST /rides  passenger='{$body['passenger_id']}'\n";
        $result = $this->requestRide->request($body['passenger_id']);
        echo "[RideHttpController] 201 Created → " . json_encode($result) . "\n";
    }

    public function patchStart(string $rideId): void
    {
        echo "[RideHttpController] PATCH /rides/{$rideId}/start\n";
        $this->startRide->start($rideId);
        echo "[RideHttpController] 200 OK → Ride started\n";
    }
}

// ─────────────────────────────────────────────
// COMPOSITION ROOT — the only place that knows about concrete classes
// In Laravel this lives in a Service Provider (register method)
// ─────────────────────────────────────────────

echo "=== Hexagonal Architecture — Ride-Hailing Service (PHP) ===\n\n";

$repository = new InMemoryRideRepository();
$notifier   = new PushNotificationAdapter();
$service    = new RideMatchingService($repository, $notifier);
$controller = new RideHttpController($service, $service);

echo "--- Scenario 1: Passenger requests a ride (driver available) ---\n";
$controller->postRide(['passenger_id' => 'passenger-42']);

echo "\n--- Scenario 2: Driver picks up the passenger ---\n";
$controller->patchStart('ride-1');

echo "\n--- Scenario 3: Second passenger requests (driver available) ---\n";
$controller->postRide(['passenger_id' => 'passenger-99']);
