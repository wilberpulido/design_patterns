<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Decorator;

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
