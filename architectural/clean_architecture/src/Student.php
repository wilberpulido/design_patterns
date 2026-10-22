<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\CleanArchitecture;

// ─────────────────────────────────────────────
// ENTITIES — enterprise-wide business rules (innermost ring)
// Stable: change only when core business rules change, not when use cases change.
// ─────────────────────────────────────────────

class Student
{
    private array $enrolledCourseIds = [];

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $email
    ) {}

    // Business rule: a student cannot enroll in the same course twice
    public function enrollIn(string $courseId): void
    {
        if (in_array($courseId, $this->enrolledCourseIds, true)) {
            throw new \DomainException("Student '{$this->id}' is already enrolled in '{$courseId}'.");
        }
        $this->enrolledCourseIds[] = $courseId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEnrolledCourseIds(): array
    {
        return $this->enrolledCourseIds;
    }
}
