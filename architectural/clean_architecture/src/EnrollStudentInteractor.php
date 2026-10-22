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

// Interactor — implements InputPort, depends on OutputPort and gateways
class EnrollStudentInteractor implements EnrollStudentInputPort
{
    // matiz: the Interactor holds the OUTPUT PORT (the presenter interface), not a return type.
    // This separates "what to compute" from "how to display it".
    // The same interactor can feed a JSON API, an HTML page, or an event queue —
    // just swap the presenter; the business logic is untouched.
    public function __construct(
        private StudentGateway $students,
        private CourseGateway $courses,
        private EnrollStudentOutputPort $presenter
    ) {}

    public function execute(EnrollStudentInput $input): void
    {
        echo "[EnrollStudentInteractor] Enrollment request: student='{$input->studentId}' course='{$input->courseId}'\n";

        $student = $this->students->findById($input->studentId);
        if ($student === null) {
            $this->presenter->presentError("Student '{$input->studentId}' not found.");

            return;
        }

        $course = $this->courses->findById($input->courseId);
        if ($course === null) {
            $this->presenter->presentError("Course '{$input->courseId}' not found.");

            return;
        }

        // Business rules enforced by the entities themselves — the interactor only orchestrates.
        // Entities signal a violated rule by throwing; translating that into an output-port
        // call is the interactor's job, so the controller never sees domain exceptions.
        // Only DomainException is caught: infrastructure failures must not be disguised as 422s.
        try {
            $student->enrollIn($course->getId());
            $course->acceptEnrollment();
        } catch (\DomainException $e) {
            // matiz: if enrollIn() succeeds but acceptEnrollment() throws, the student was already
            // mutated in memory (and the in-memory gateway holds that same reference), leaving a
            // half-applied change. Real apps wrap this in a transaction (DB::transaction() in
            // Laravel) or validate every rule before mutating any entity.
            echo "[EnrollStudentInteractor] Business rule violated — routing it to the presenter\n";
            $this->presenter->presentError($e->getMessage());

            return;
        }

        $this->students->save($student);
        $this->courses->save($course);

        echo "[EnrollStudentInteractor] Enrollment persisted — pushing output to presenter\n";

        // Use case PUSHES to the presenter instead of returning — the output boundary is explicit
        $this->presenter->presentSuccess(new EnrollStudentOutput(
            studentName: $student->getName(),
            courseTitle: $course->getTitle(),
            enrollmentCount: $course->getEnrollmentCount(),
            capacity: $course->getCapacity(),
        ));
    }
}
