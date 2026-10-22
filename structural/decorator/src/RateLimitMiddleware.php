<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Decorator;

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
