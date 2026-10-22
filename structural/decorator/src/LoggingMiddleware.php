<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Decorator;

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
