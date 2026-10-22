<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Architectural\Hexagonal;

use DesignPatterns\Architectural\Hexagonal\DriverNotifierPort;
use DesignPatterns\Architectural\Hexagonal\Ride;
use DesignPatterns\Architectural\Hexagonal\RideMatchingService;
use DesignPatterns\Architectural\Hexagonal\RideRepositoryPort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RideMatchingService::class)]
#[CoversClass(Ride::class)]
final class RideMatchingServiceTest extends TestCase
{
    private object $notifier;

    // Test doubles plug into the DRIVEN (secondary) ports: the core never knows
    // whether it talks to a real database/push service or to these fakes.
    private function service(?string $availableDriver): RideMatchingService
    {
        $rides = new class($availableDriver) implements RideRepositoryPort
        {
            public array $store = [];

            public function __construct(private ?string $driver) {}

            public function nextId(): string
            {
                return 'ride-1';
            }

            public function save(Ride $ride): void
            {
                $this->store[$ride->getId()] = $ride;
            }

            public function findById(string $id): ?Ride
            {
                return $this->store[$id] ?? null;
            }

            public function findAvailableDriverId(): ?string
            {
                return $this->driver;
            }
        };
        $this->notifier = new class implements DriverNotifierPort
        {
            public array $assigned = [];

            public function notifyAssignment(string $driverId, string $rideId): void
            {
                $this->assigned[] = $driverId;
            }

            public function notifyRideStarted(string $passengerId, string $rideId): void {}
        };

        return new RideMatchingService($rides, $this->notifier);
    }

    public function test_request_assigns_an_available_driver_and_notifies_them(): void
    {
        $this->expectOutputRegex('/created for passenger/');

        // The test drives the core through its DRIVING (primary) port, like the HTTP controller does.
        $ride = $this->service('driver-7')->request('passenger-1');

        $this->assertSame('driver-7', $ride['driver']);
        $this->assertSame(['driver-7'], $this->notifier->assigned);
    }

    public function test_without_drivers_nobody_is_notified(): void
    {
        $this->expectOutputRegex('/created for passenger/');

        $ride = $this->service(null)->request('passenger-1');

        $this->assertNull($ride['driver']);
        $this->assertSame([], $this->notifier->assigned);
    }

    public function test_domain_forbids_starting_an_unaccepted_ride(): void
    {
        $this->expectException(\DomainException::class);

        (new Ride('ride-9', 'passenger-1'))->start();
    }
}
