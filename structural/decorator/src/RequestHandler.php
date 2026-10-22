<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Decorator;

interface RequestHandler
{
    public function handle(array $request): string;
}
