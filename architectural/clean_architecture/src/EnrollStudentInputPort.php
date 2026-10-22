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

// Input port — the Controller calls this
interface EnrollStudentInputPort
{
    public function execute(EnrollStudentInput $input): void;
}
