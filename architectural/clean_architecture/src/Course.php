<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\CleanArchitecture;

// ─────────────────────────────────────────────
// ENTITIES — enterprise-wide business rules (innermost ring)
// Stable: change only when core business rules change, not when use cases change.
// ─────────────────────────────────────────────

class Course
{
    private int $enrollmentCount = 0;

    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly int $capacity
    ) {}

    // Business rule: a course cannot exceed its capacity
    public function acceptEnrollment(): void
    {
        if ($this->enrollmentCount >= $this->capacity) {
            throw new \DomainException("Course '{$this->id}' is at capacity ({$this->capacity}).");
        }
        $this->enrollmentCount++;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function getEnrollmentCount(): int
    {
        return $this->enrollmentCount;
    }
}
