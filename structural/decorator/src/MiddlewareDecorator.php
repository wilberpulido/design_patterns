<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Decorator;

// Base decorator: holds a reference to the next handler in the chain.
// All middleware extend this to avoid repeating the constructor.
abstract class MiddlewareDecorator implements RequestHandler
{
    public function __construct(protected RequestHandler $next) {}
}
