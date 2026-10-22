<?php

declare(strict_types=1);

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

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   RING 1 ENTITIES      Student.php, Course.php
//   RING 2 USE CASES     EnrollStudentInteractor.php + its boundaries: EnrollStudentInputPort.php,
//                        EnrollStudentOutputPort.php, EnrollStudentInput.php,
//                        EnrollStudentOutput.php, StudentGateway.php, CourseGateway.php
//   RING 3 ADAPTERS      EnrollmentController.php, JsonEnrollmentPresenter.php,
//                        InMemoryStudentGateway.php, InMemoryCourseGateway.php
//   RING 4 FRAMEWORKS    the composition root at the bottom of this file
// Executable guarantees of the pattern: tests/EnrollStudentInteractorTest.php

require __DIR__.'/../../vendor/autoload.php';

use DesignPatterns\Architectural\CleanArchitecture\Course;
use DesignPatterns\Architectural\CleanArchitecture\EnrollmentController;
use DesignPatterns\Architectural\CleanArchitecture\EnrollStudentInteractor;
use DesignPatterns\Architectural\CleanArchitecture\InMemoryCourseGateway;
use DesignPatterns\Architectural\CleanArchitecture\InMemoryStudentGateway;
use DesignPatterns\Architectural\CleanArchitecture\JsonEnrollmentPresenter;
use DesignPatterns\Architectural\CleanArchitecture\Student;

// ─────────────────────────────────────────────
// FRAMEWORKS & DRIVERS — outermost ring (Composition Root)
// In a real app: Laravel, Symfony, Doctrine ORM, etc.
// ─────────────────────────────────────────────

echo "=== Clean Architecture — E-Learning Enrollment Platform (PHP) ===\n\n";

$studentGateway = new InMemoryStudentGateway;
$courseGateway = new InMemoryCourseGateway;

$studentGateway->seed(new Student('s-001', 'Alice Martínez', 'alice@example.com'));
$studentGateway->seed(new Student('s-002', 'Bob Chen', 'bob@example.com'));
$courseGateway->seed(new Course('c-101', 'Python for Data Science', 2));

$presenter = new JsonEnrollmentPresenter;
$interactor = new EnrollStudentInteractor($studentGateway, $courseGateway, $presenter);
$controller = new EnrollmentController($interactor, $presenter);

echo "--- Alice enrolls in Python for Data Science ---\n";
$controller->postEnrollment(['student_id' => 's-001', 'course_id' => 'c-101']);

echo "\n--- Bob enrolls in the same course ---\n";
$controller->postEnrollment(['student_id' => 's-002', 'course_id' => 'c-101']);

echo "\n--- Alice tries to enroll again (entity invariant) ---\n";
$controller->postEnrollment(['student_id' => 's-001', 'course_id' => 'c-101']);
