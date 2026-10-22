<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\CleanArchitecture;

// ─────────────────────────────────────────────
// INTERFACE ADAPTERS — third ring (Controllers, Presenters, Gateways)
// Convert data between use-case format and external formats (HTTP, DB)
// ─────────────────────────────────────────────

// Controller — converts HTTP input into InputData and invokes the use case
class EnrollmentController
{
    public function __construct(
        private EnrollStudentInputPort $useCase,
        private JsonEnrollmentPresenter $presenter
    ) {}

    public function postEnrollment(array $request): array
    {
        echo "[EnrollmentController] POST /enrollments  student='{$request['student_id']}' course='{$request['course_id']}'\n";

        // Controller converts HTTP body into use-case InputData — these are different objects
        $input = new EnrollStudentInput($request['student_id'], $request['course_id']);
        $this->useCase->execute($input);

        // Controller reads the ViewModel from the presenter after the use case runs
        $vm = $this->presenter->getViewModel();
        $httpStatus = isset($vm['error']) ? 422 : 201;
        echo "[EnrollmentController] HTTP {$httpStatus} → ".json_encode($vm)."\n";

        return $vm ?? [];
    }
}
