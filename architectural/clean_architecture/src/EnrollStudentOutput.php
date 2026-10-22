<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\CleanArchitecture;

// ─────────────────────────────────────────────
// USE CASES — application-specific business rules (second ring)
//
// Clean Architecture defines EXPLICIT data structures for every boundary crossing:
//   - InputData  : crosses from Controller into the Use Case
//   - OutputData : crosses from the Use Case into the Presenter
//   - InputPort  : interface the Controller calls
//   - OutputPort : interface the Presenter implements (use case "pushes" data, never returns it)
// ─────────────────────────────────────────────

class EnrollStudentOutput
{
    public function __construct(
        public readonly string $studentName,
        public readonly string $courseTitle,
        public readonly int $enrollmentCount,
        public readonly int $capacity
    ) {}
}
