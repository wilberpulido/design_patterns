<?php

declare(strict_types=1);

namespace DesignPatterns\Creational\Singleton;

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
