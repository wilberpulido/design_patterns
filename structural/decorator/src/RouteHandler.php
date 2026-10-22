<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Decorator;

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
