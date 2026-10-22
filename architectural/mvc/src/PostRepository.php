<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Mvc;

// ─── Model ────────────────────────────────────────────────────────────────────
// The model owns data and business rules.
// It knows nothing about how its data will be displayed.

// matiz: separating storage from the domain object (Post) lets you swap
// the data source (array → MySQL → Redis) without touching Post or PostController.
// This is the Repository sub-pattern working inside the Model layer.
class PostRepository
{
    /** @var Post[] */
    private array $posts = [];

    private int $nextId = 1;

    public function create(string $author, string $title, string $content): Post
    {
        $post = new Post($this->nextId++, $author, $title, $content);
        $this->posts[$post->id] = $post;

        return $post;
    }

    public function findById(int $id): ?Post
    {
        return $this->posts[$id] ?? null;
    }

    /** @return Post[] */
    public function findAll(): array
    {
        return array_values($this->posts);
    }

    /** @return Post[] */
    public function findByStatus(string $status): array
    {
        return array_values(
            array_filter($this->posts, fn (Post $p) => $p->getStatus() === $status)
        );
    }
}
