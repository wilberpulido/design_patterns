<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Creational\Singleton;

use DesignPatterns\Creational\Singleton\AuditLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Required by requireCoverageMetadata="true" in phpunit.xml:
// every test class must declare which production class it exercises.
#[CoversClass(AuditLogger::class)]
final class AuditLoggerTest extends TestCase
{
  // matiz: PHPUnit runs every test in the SAME PHP process, so a static
  // $instance survives from one test to the next and leaks its entries.
  // That's why Singletons are called "global state in disguise": tests become
  // order-dependent. Reflection lets us reset the private static slot so each
  // test starts from a clean state — something production code must never do.
  protected function setUp(): void
  {
    (new \ReflectionProperty(AuditLogger::class, 'instance'))->setValue(null, null);
  }

  // AuditLogger narrates every step with echo (CLAUDE.md requires it).
  // beStrictAboutOutputDuringTests="true" marks unexpected output as risky,
  // so each test declares the narration it expects instead of hiding it.
  private function expectNarration(): void
  {
    $this->expectOutputRegex('/\[AuditLogger\]/');
  }

  public function test_get_instance_returns_same_object(): void
  {
    $this->expectNarration();

    $fromAuth = AuditLogger::getInstance();
    $fromPayment = AuditLogger::getInstance();

    // assertSame compares identity (===), not equality (==):
    // two different objects with the same data would fail here — that's the point.
    $this->assertSame($fromAuth, $fromPayment);
  }

  public function test_entries_are_shared_between_references(): void
  {
    $this->expectNarration();

    $fromAuth = AuditLogger::getInstance();
    $fromPayment = AuditLogger::getInstance();

    $fromAuth->record('jane.doe', 'logged in');
    $fromPayment->record('jane.doe', 'exported invoice #4821');

    // Both references write into the same $entries array.
    $this->assertSame(2, $fromAuth->getEntryCount());
  }

  public function test_new_request_starts_with_empty_audit_trail(): void
  {
    $this->expectNarration();

    // Each test simulates a fresh request: nothing should be recorded yet.
    $this->assertSame(0, AuditLogger::getInstance()->getEntryCount());
  }

  public function test_unserialize_cannot_create_a_second_instance(): void
  {
    $this->expectNarration();
    $this->expectException(\Exception::class);

    $serialized = serialize(AuditLogger::getInstance());
    unserialize($serialized); // __wakeup() must throw here
  }
}
