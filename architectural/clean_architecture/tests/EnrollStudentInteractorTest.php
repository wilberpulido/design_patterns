<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Architectural\CleanArchitecture;

use DesignPatterns\Architectural\CleanArchitecture\Course;
use DesignPatterns\Architectural\CleanArchitecture\EnrollStudentInput;
use DesignPatterns\Architectural\CleanArchitecture\EnrollStudentInteractor;
use DesignPatterns\Architectural\CleanArchitecture\EnrollStudentOutput;
use DesignPatterns\Architectural\CleanArchitecture\EnrollStudentOutputPort;
use DesignPatterns\Architectural\CleanArchitecture\InMemoryCourseGateway;
use DesignPatterns\Architectural\CleanArchitecture\InMemoryStudentGateway;
use DesignPatterns\Architectural\CleanArchitecture\Student;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnrollStudentInteractor::class)]
final class EnrollStudentInteractorTest extends TestCase
{
    private object $presenter;

    private function interactor(int $capacity = 2): EnrollStudentInteractor
    {
        $students = new InMemoryStudentGateway;
        $students->seed(new Student('s-1', 'Alice', 'alice@example.com'));
        $students->seed(new Student('s-2', 'Bob', 'bob@example.com'));
        $courses = new InMemoryCourseGateway;
        $courses->seed(new Course('c-1', 'Clean Architecture', $capacity));

        // Spy presenter implementing the OUTPUT PORT: the interactor pushes its result
        // here instead of returning it, so this is where the test reads the outcome.
        $this->presenter = new class implements EnrollStudentOutputPort
        {
            public ?EnrollStudentOutput $success = null;

            public ?string $error = null;

            public function presentSuccess(EnrollStudentOutput $output): void
            {
                $this->success = $output;
            }

            public function presentError(string $message): void
            {
                $this->error = $message;
            }
        };

        return new EnrollStudentInteractor($students, $courses, $this->presenter);
    }

    public function test_successful_enrollment_is_pushed_to_the_presenter(): void
    {
        $this->expectOutputRegex('/pushing output to presenter/');

        $this->interactor()->execute(new EnrollStudentInput('s-1', 'c-1'));

        $this->assertNull($this->presenter->error);
        $this->assertSame('Alice', $this->presenter->success?->studentName);
        $this->assertSame(1, $this->presenter->success?->enrollmentCount);
    }

    public function test_entity_rule_violation_becomes_a_presenter_error(): void
    {
        // Regression test for the bug the CI smoke run caught: the DomainException
        // thrown by the entity must not escape the interactor.
        $this->expectOutputRegex('/Business rule violated/');
        $interactor = $this->interactor();
        $interactor->execute(new EnrollStudentInput('s-1', 'c-1'));

        $interactor->execute(new EnrollStudentInput('s-1', 'c-1'));

        $this->assertStringContainsString('already enrolled', (string) $this->presenter->error);
    }

    public function test_unknown_student_is_reported_through_the_output_port(): void
    {
        $this->expectOutputRegex('/Enrollment request/');

        $this->interactor()->execute(new EnrollStudentInput('s-404', 'c-1'));

        $this->assertStringContainsString('not found', (string) $this->presenter->error);
    }
}
