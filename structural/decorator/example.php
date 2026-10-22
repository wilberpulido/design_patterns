<?php

declare(strict_types=1);

/**
 * DECORATOR PATTERN - PHP
 *
 * Laravel note: Laravel's HTTP middleware stack IS the Decorator pattern.
 * In app/Http/Kernel.php, each middleware wraps the next one via the $next closure.
 * Auth, Throttle, CORS, Sanctum — all decorators layered on top of the route handler.
 * The order in $middleware[] defines the wrapping order (outermost runs first).
 *
 * Scenario: HTTP Request Pipeline — logging, rate limiting, and authentication
 * applied as decorators to a core route handler.
 */

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/RequestHandler.php         Component — the interface shared by the core and every decorator
//   src/RouteHandler.php           Concrete component — the real work at the center of the onion
//   src/MiddlewareDecorator.php    Base decorator — holds $next and forwards to it by default
//   src/LoggingMiddleware.php      Concrete decorators — each adds ONE behavior around $next
//   src/RateLimitMiddleware.php
//   src/AuthMiddleware.php
// Executable guarantees of the pattern: tests/MiddlewareDecoratorTest.php

require __DIR__ . '/../../vendor/autoload.php';

use DesignPatterns\Structural\Decorator\AuthMiddleware;
use DesignPatterns\Structural\Decorator\LoggingMiddleware;
use DesignPatterns\Structural\Decorator\RateLimitMiddleware;
use DesignPatterns\Structural\Decorator\RouteHandler;

// matiz: wrapping order matters — the outermost decorator runs first.
// Here: Logging → RateLimit → Auth → RouteHandler
// Logging wraps everything, so it captures even blocked requests and their timing.
// RateLimit runs before auth, so even unauthorized probes count against the quota.
// Swapping the order changes behavior without modifying any class.

$pipeline = new LoggingMiddleware(
    new RateLimitMiddleware(
        new AuthMiddleware(
            new RouteHandler()
        ),
        limit: 3
    )
);

echo "=== Decorator Pattern Demo — HTTP Middleware Pipeline (PHP) ===\n\n";

echo "-- Request 1: valid token --\n";
$pipeline->handle(['method' => 'GET', 'path' => '/api/profile', 'token' => 'tok_abc123']);

echo "\n-- Request 2: missing token --\n";
$pipeline->handle(['method' => 'GET', 'path' => '/api/profile', 'token' => '']);

echo "\n-- Request 3: valid token again --\n";
$pipeline->handle(['method' => 'GET', 'path' => '/api/profile', 'token' => 'tok_abc123']);

echo "\n-- Request 4: rate limit kicks in --\n";
$pipeline->handle(['method' => 'GET', 'path' => '/api/profile', 'token' => 'tok_abc123']);
