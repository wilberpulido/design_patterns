<?php

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

interface RequestHandler
{
    public function handle(array $request): string;
}

// The core handler — knows nothing about auth, logging, or rate limits.
// Its only job is to process the business request.
class RouteHandler implements RequestHandler
{
    public function handle(array $request): string
    {
        echo "[RouteHandler] Executing business logic for '{$request['path']}'...\n";
        return json_encode(['status' => 200, 'data' => 'resource loaded']);
    }
}

// Base decorator: holds a reference to the next handler in the chain.
// All middleware extend this to avoid repeating the constructor.
abstract class MiddlewareDecorator implements RequestHandler
{
    public function __construct(protected RequestHandler $next) {}
}

class LoggingMiddleware extends MiddlewareDecorator
{
    public function handle(array $request): string
    {
        $start = microtime(true);
        echo "[LoggingMiddleware] → {$request['method']} {$request['path']}\n";
        $response = $this->next->handle($request);
        $ms = round((microtime(true) - $start) * 1000, 2);
        echo "[LoggingMiddleware] ← Response in {$ms}ms\n";
        return $response;
    }
}

class RateLimitMiddleware extends MiddlewareDecorator
{
    private static int $count = 0;

    public function __construct(RequestHandler $next, private int $limit = 3)
    {
        parent::__construct($next);
    }

    public function handle(array $request): string
    {
        self::$count++;
        echo "[RateLimitMiddleware] Request #" . self::$count . " (limit: {$this->limit})\n";

        if (self::$count > $this->limit) {
            echo "[RateLimitMiddleware] Limit exceeded — blocking.\n";
            return json_encode(['status' => 429, 'message' => 'Too Many Requests']);
        }

        return $this->next->handle($request);
    }
}

class AuthMiddleware extends MiddlewareDecorator
{
    public function handle(array $request): string
    {
        echo "[AuthMiddleware] Validating token...\n";

        if (empty($request['token'])) {
            echo "[AuthMiddleware] Missing token — access denied.\n";
            return json_encode(['status' => 401, 'message' => 'Unauthorized']);
        }

        echo "[AuthMiddleware] Token valid — forwarding.\n";
        return $this->next->handle($request);
    }
}

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
