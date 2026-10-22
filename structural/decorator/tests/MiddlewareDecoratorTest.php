<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Structural\Decorator;

use DesignPatterns\Structural\Decorator\AuthMiddleware;
use DesignPatterns\Structural\Decorator\LoggingMiddleware;
use DesignPatterns\Structural\Decorator\MiddlewareDecorator;
use DesignPatterns\Structural\Decorator\RateLimitMiddleware;
use DesignPatterns\Structural\Decorator\RouteHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewareDecorator::class)]
#[CoversClass(AuthMiddleware::class)]
#[CoversClass(RateLimitMiddleware::class)]
#[CoversClass(LoggingMiddleware::class)]
#[CoversClass(RouteHandler::class)]
final class MiddlewareDecoratorTest extends TestCase
{
    // matiz: RateLimitMiddleware counts requests in a private STATIC property, so the
    // counter leaks between tests exactly like the Singleton did. Reset it every time.
    protected function setUp(): void
    {
        (new \ReflectionProperty(RateLimitMiddleware::class, 'count'))->setValue(null, 0);
    }

    public function test_each_decorator_can_short_circuit_the_chain(): void
    {
        $this->expectOutputRegex('/Missing token — access denied/');

        $pipeline = new LoggingMiddleware(new AuthMiddleware(new RouteHandler));

        $response = json_decode($pipeline->handle(['method' => 'GET', 'path' => '/api/profile']), true);

        $this->assertSame(401, $response['status']);
    }

    public function test_decorators_wrap_the_same_interface_in_any_order(): void
    {
        $this->expectOutputRegex('/Executing business logic/');

        // Same building blocks, different composition — no subclass per combination.
        $pipeline = new RateLimitMiddleware(new AuthMiddleware(new RouteHandler), limit: 1);
        $request = ['method' => 'GET', 'path' => '/api/profile', 'token' => 'valid'];

        $first = json_decode($pipeline->handle($request), true);
        $second = json_decode($pipeline->handle($request), true);

        $this->assertSame(200, $first['status']);
        $this->assertSame(429, $second['status']);
    }
}
