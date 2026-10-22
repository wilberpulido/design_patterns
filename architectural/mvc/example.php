<?php

declare(strict_types=1);

/**
 * Laravel note:
 *
 * How Laravel applies MVC natively:
 * - Models:      Eloquent classes in app/Models/ represent database tables and
 *                encapsulate business rules (relationships, scopes, accessors).
 * - Views:       Blade templates in resources/views/ handle all rendering.
 *                Components and layouts keep the presentation layer composable.
 * - Controllers: Classes in app/Http/Controllers/ receive HTTP requests via routes,
 *                query models, and return views or JSON responses.
 *                Route-model binding automatically resolves model instances.
 *
 * Where it makes sense to apply MVC yourself in a Laravel project:
 * - Console commands that display formatted reports: use a dedicated View class
 *   instead of echoing directly inside the command (command acts as Controller).
 * - Admin panels with complex multi-step workflows: one controller per workflow step
 *   (e.g. OrderApprovalController) keeps each step isolated and testable.
 * - API versioning: separate transformer/resource classes per version so the
 *   Model is never coupled to a specific response shape.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/Post.php                   Model — data + business rules (e.g. can't publish twice)
//   src/PostRepository.php         Model persistence
//   src/PostView.php               View — presentation only, no decisions
//   src/PostController.php         Controller — receives input, coordinates Model and View
// Executable guarantees of the pattern: tests/PostControllerTest.php

require __DIR__ . '/../../vendor/autoload.php';

use DesignPatterns\Architectural\Mvc\PostController;
use DesignPatterns\Architectural\Mvc\PostRepository;
use DesignPatterns\Architectural\Mvc\PostView;

// ─── Bootstrap ────────────────────────────────────────────────────────────────

echo "=== Blog CMS — MVC Pattern ===\n";

$repository = new PostRepository();
$view       = new PostView();
$controller = new PostController($repository, $view);

// Seed posts (in a real app this comes from the database)
$repository->create('alice', 'Getting Started with MVC', 'MVC separates concerns into three layers...');
$repository->create('bob',   'PHP 8 Features',           'Fibers, enums, named arguments...');
$repository->create('alice', 'Deploying to Production',  'Use Docker for consistent environments...');

// Simulate user actions (in a real app these come from HTTP routes)
$controller->index();
$controller->drafts();
$controller->show(2);
$controller->publish(1);
$controller->publish(1); // already published — the model enforces the rule
$controller->show(99);   // not found
