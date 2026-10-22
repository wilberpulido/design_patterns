<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Architectural\Mvc;

use DesignPatterns\Architectural\Mvc\Post;
use DesignPatterns\Architectural\Mvc\PostController;
use DesignPatterns\Architectural\Mvc\PostRepository;
use DesignPatterns\Architectural\Mvc\PostView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostController::class)]
#[CoversClass(Post::class)]
final class PostControllerTest extends TestCase
{
    public function test_model_enforces_its_own_business_rule(): void
    {
        $post = new Post(1, 'ana', 'Title', 'Body');
        $post->publish();

        $this->expectException(\LogicException::class);
        $post->publish();
    }

    public function test_controller_turns_a_model_rule_violation_into_a_view_error(): void
    {
        // The controller never decides the rule — it only routes the model's answer to the view.
        $this->expectOutputRegex('/already published/');
        $repository = new PostRepository();
        $post = $repository->create('ana', 'MVC in PHP', 'Content');
        $controller = new PostController($repository, new PostView());

        $controller->publish($post->id);
        $controller->publish($post->id);

        $this->assertSame('published', $post->getStatus());
    }
}
