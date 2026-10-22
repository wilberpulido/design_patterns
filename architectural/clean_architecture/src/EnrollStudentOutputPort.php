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

// Output port — the use case calls this to deliver results
// The Presenter implements it; the Interactor never returns a value directly.
interface EnrollStudentOutputPort
{
    public function presentSuccess(EnrollStudentOutput $output): void;
    public function presentError(string $message): void;
}
