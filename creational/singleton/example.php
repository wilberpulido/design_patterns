<?php

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

class AuditLogger
{
    private static ?AuditLogger $instance = null;

    private array $entries = [];
    private string $sessionId;

    private function __construct()
    {
        // Each instance would have a different sessionId — that's exactly what we want to avoid.
        $this->sessionId = 'session_' . uniqid();
        echo "[AuditLogger] Logger initialized for session: {$this->sessionId}\n";
    }

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            echo "[AuditLogger] No instance found — creating one...\n";
            static::$instance = new static();
        } else {
            echo "[AuditLogger] Instance already exists — returning the same one.\n";
        }

        return static::$instance;
    }

    public function record(string $user, string $action): void
    {
        $entry = "[{$this->sessionId}] User '{$user}' → {$action}";
        $this->entries[] = $entry;
        echo "[AuditLogger] Recorded: {$entry}\n";
    }

    public function flushToStorage(): void
    {
        echo "[AuditLogger] Flushing " . count($this->entries) . " entries to persistent storage...\n";
        foreach ($this->entries as $i => $entry) {
            echo "[AuditLogger] → [{$i}] {$entry}\n";
        }
    }

    public function getEntryCount(): int
    {
        return count($this->entries);
    }

    // Prevent cloning — a clone would be a second instance with a separate $entries array.
    private function __clone() {}

    /**
     * matiz: PHP's unserialize() reconstructs objects by bypassing the constructor entirely.
     * Without this guard, someone could serialize the singleton, store it, and later
     * deserialize it — producing a SECOND instance with an empty $entries array,
     * silently breaking the uniqueness guarantee.
     * Throwing here makes the violation visible instead of letting it fail silently.
     */
    public function __wakeup(): never
    {
        throw new \Exception("[AuditLogger] Cannot deserialize a Singleton — this would create a second instance.");
    }
}

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
