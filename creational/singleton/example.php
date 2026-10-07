<?php

declare(strict_types=1);
/**
 * LARAVEL NOTE:
 * Laravel applies this pattern in its Service Container via singleton bindings.
 * Example: AppServiceProvider registers app-wide services as singletons:
 *   $this->app->singleton(AuditLogger::class, fn() => new AuditLogger());
 * From that point, every call to app(AuditLogger::class) or constructor injection
 * returns the SAME instance — no matter where in the app it's requested.
 */

/**
 * SINGLETON PATTERN - PHP
 * Scenario: AuditLogger — records every sensitive user action (login, permission change,
 * data export) to a central audit trail during a request lifecycle.
 *
 * Why Singleton here?
 * - All audit entries must share the same session context.
 * - Multiple instances would produce fragmented or duplicated log entries.
 * - The logger must be accessible from anywhere: controllers, services, jobs.
 */

require __DIR__ . '/../../vendor/autoload.php';

use DesignPatterns\Creational\Singleton\AuditLogger;

// --- Execution ---

echo "=== Singleton Pattern Demo — AuditLogger (PHP) ===\n\n";

echo "-- AuthController requests the logger --\n";
$loggerFromAuth = AuditLogger::getInstance();

echo "\n-- PaymentService requests the logger --\n";
$loggerFromPayment = AuditLogger::getInstance();

echo "\n-- Are both services using the same logger? --\n";
echo ($loggerFromAuth === $loggerFromPayment ? "YES — same instance" : "NO — different instances") . "\n";

echo "\n-- AuthController records a login --\n";
$loggerFromAuth->record('jane.doe', 'logged in');

echo "\n-- PaymentService records a sensitive action --\n";
$loggerFromPayment->record('jane.doe', 'exported invoice #4821');

echo "\n-- Total entries across both references: " . $loggerFromAuth->getEntryCount() . " --\n";
echo "(Both references see the same 2 entries because it's the same object)\n";

echo "\n-- Flushing all entries at end of request --\n";
$loggerFromAuth->flushToStorage();

echo "\n-- Demonstrating the matiz: attempting to deserialize the singleton --\n";
try {
    $serialized = serialize($loggerFromAuth);
    unserialize($serialized);
} catch (\Exception $e) {
    echo "[AuditLogger] Caught: " . $e->getMessage() . "\n";
}
