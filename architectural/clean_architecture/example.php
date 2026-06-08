<?php

/**
 * LARAVEL NOTE
 *
 * HOW LARAVEL RELATES TO CLEAN ARCHITECTURE:
 * - Eloquent models blend Entity + Infrastructure — strict Clean Architecture keeps entities
 *   as plain PHP classes free of any ORM base class.
 * - Form Requests validate and shape HTTP input before it reaches the use case (Controller role).
 * - API Resources (JsonResource) are Presenters: they convert raw output into the JSON shape.
 * - Service Providers act as the Composition Root, binding interfaces to implementations.
 *
 * WHERE TO APPLY IT IN LARAVEL:
 * - App\Domain\Entities: pure PHP entities (no Eloquent).
 * - App\UseCases: Interactors with InputData / OutputPort.
 * - App\Http\Controllers: receive HTTP, build InputData, call the Interactor.
 * - App\Http\Presenters (or Resources): implement OutputPort, shape JSON responses.
 * - App\Infrastructure\Repositories: Eloquent implementations of gateway interfaces.
 */

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

    public function getId(): string              { return $this->id; }
    public function getName(): string            { return $this->name; }
    public function getEmail(): string           { return $this->email; }
    public function getEnrolledCourseIds(): array { return $this->enrolledCourseIds; }
}

class Course
{
    private int $enrollmentCount = 0;

    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly int    $capacity
    ) {}

    // Business rule: a course cannot exceed its capacity
    public function acceptEnrollment(): void
    {
        if ($this->enrollmentCount >= $this->capacity) {
            throw new \DomainException("Course '{$this->id}' is at capacity ({$this->capacity}).");
        }
        $this->enrollmentCount++;
    }

    public function getId(): string    { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getCapacity(): int { return $this->capacity; }
    public function getEnrollmentCount(): int { return $this->enrollmentCount; }
}

// ─────────────────────────────────────────────
// USE CASES — application-specific business rules (second ring)
//
// Clean Architecture defines EXPLICIT data structures for every boundary crossing:
//   - InputData  : crosses from Controller into the Use Case
//   - OutputData : crosses from the Use Case into the Presenter
//   - InputPort  : interface the Controller calls
//   - OutputPort : interface the Presenter implements (use case "pushes" data, never returns it)
// ─────────────────────────────────────────────

// Boundary DTOs — plain data, no business logic
class EnrollStudentInput
{
    public function __construct(
        public readonly string $studentId,
        public readonly string $courseId
    ) {}
}

class EnrollStudentOutput
{
    public function __construct(
        public readonly string $studentName,
        public readonly string $courseTitle,
        public readonly int    $enrollmentCount,
        public readonly int    $capacity
    ) {}
}

// Output port — the use case calls this to deliver results
// The Presenter implements it; the Interactor never returns a value directly.
interface EnrollStudentOutputPort
{
    public function presentSuccess(EnrollStudentOutput $output): void;
    public function presentError(string $message): void;
}

// Input port — the Controller calls this
interface EnrollStudentInputPort
{
    public function execute(EnrollStudentInput $input): void;
}

// Gateways — defined in the use case ring, implemented in the infrastructure ring
interface StudentGateway
{
    public function findById(string $id): ?Student;
    public function save(Student $student): void;
}

interface CourseGateway
{
    public function findById(string $id): ?Course;
    public function save(Course $course): void;
}

// Interactor — implements InputPort, depends on OutputPort and gateways
class EnrollStudentInteractor implements EnrollStudentInputPort
{
    // matiz: the Interactor holds the OUTPUT PORT (the presenter interface), not a return type.
    // This separates "what to compute" from "how to display it".
    // The same interactor can feed a JSON API, an HTML page, or an event queue —
    // just swap the presenter; the business logic is untouched.
    public function __construct(
        private StudentGateway          $students,
        private CourseGateway           $courses,
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

        // Business rules enforced by the entities themselves — the interactor only orchestrates
        $student->enrollIn($course->getId());
        $course->acceptEnrollment();

        $this->students->save($student);
        $this->courses->save($course);

        echo "[EnrollStudentInteractor] Enrollment persisted — pushing output to presenter\n";

        // Use case PUSHES to the presenter instead of returning — the output boundary is explicit
        $this->presenter->presentSuccess(new EnrollStudentOutput(
            studentName:     $student->getName(),
            courseTitle:     $course->getTitle(),
            enrollmentCount: $course->getEnrollmentCount(),
            capacity:        $course->getCapacity(),
        ));
    }
}

// ─────────────────────────────────────────────
// INTERFACE ADAPTERS — third ring (Controllers, Presenters, Gateways)
// Convert data between use-case format and external formats (HTTP, DB)
// ─────────────────────────────────────────────

// Gateways (infrastructure adapters)
class InMemoryStudentGateway implements StudentGateway
{
    private array $store = [];

    public function seed(Student $s): void { $this->store[$s->getId()] = $s; }

    public function findById(string $id): ?Student { return $this->store[$id] ?? null; }

    public function save(Student $s): void
    {
        echo "[InMemoryStudentGateway] Saved student '{$s->getId()}'\n";
        $this->store[$s->getId()] = $s;
    }
}

class InMemoryCourseGateway implements CourseGateway
{
    private array $store = [];

    public function seed(Course $c): void { $this->store[$c->getId()] = $c; }

    public function findById(string $id): ?Course { return $this->store[$id] ?? null; }

    public function save(Course $c): void
    {
        $ratio = "{$c->getEnrollmentCount()}/{$c->getCapacity()}";
        echo "[InMemoryCourseGateway] Saved course '{$c->getId()}' — enrolled: {$ratio}\n";
        $this->store[$c->getId()] = $c;
    }
}

// Presenter — implements OutputPort, shapes data into the view model the controller will return
class JsonEnrollmentPresenter implements EnrollStudentOutputPort
{
    private ?array $viewModel = null;

    public function presentSuccess(EnrollStudentOutput $output): void
    {
        // matiz: the presenter converts OutputData into the format the delivery mechanism needs.
        // A different presenter could format the same OutputData as an HTML confirmation page.
        $this->viewModel = [
            'status'        => 'enrolled',
            'student'       => $output->studentName,
            'course'        => $output->courseTitle,
            'seats_taken'   => "{$output->enrollmentCount}/{$output->capacity}",
        ];
        echo "[JsonEnrollmentPresenter] Shaped success ViewModel: " . json_encode($this->viewModel) . "\n";
    }

    public function presentError(string $message): void
    {
        $this->viewModel = ['error' => $message];
        echo "[JsonEnrollmentPresenter] Shaped error ViewModel: " . json_encode($this->viewModel) . "\n";
    }

    public function getViewModel(): ?array { return $this->viewModel; }
}

// Controller — converts HTTP input into InputData and invokes the use case
class EnrollmentController
{
    public function __construct(
        private EnrollStudentInputPort  $useCase,
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
        echo "[EnrollmentController] HTTP {$httpStatus} → " . json_encode($vm) . "\n";

        return $vm ?? [];
    }
}

// ─────────────────────────────────────────────
// FRAMEWORKS & DRIVERS — outermost ring (Composition Root)
// In a real app: Laravel, Symfony, Doctrine ORM, etc.
// ─────────────────────────────────────────────

echo "=== Clean Architecture — E-Learning Enrollment Platform (PHP) ===\n\n";

$studentGateway = new InMemoryStudentGateway();
$courseGateway  = new InMemoryCourseGateway();

$studentGateway->seed(new Student('s-001', 'Alice Martínez', 'alice@example.com'));
$studentGateway->seed(new Student('s-002', 'Bob Chen', 'bob@example.com'));
$courseGateway->seed(new Course('c-101', 'Python for Data Science', 2));

$presenter   = new JsonEnrollmentPresenter();
$interactor  = new EnrollStudentInteractor($studentGateway, $courseGateway, $presenter);
$controller  = new EnrollmentController($interactor, $presenter);

echo "--- Alice enrolls in Python for Data Science ---\n";
$controller->postEnrollment(['student_id' => 's-001', 'course_id' => 'c-101']);

echo "\n--- Bob enrolls in the same course ---\n";
$controller->postEnrollment(['student_id' => 's-002', 'course_id' => 'c-101']);

echo "\n--- Alice tries to enroll again (entity invariant) ---\n";
$controller->postEnrollment(['student_id' => 's-001', 'course_id' => 'c-101']);
