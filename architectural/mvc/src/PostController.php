<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Mvc;

// ─── Controller ───────────────────────────────────────────────────────────────
// The controller handles user intent (HTTP request, CLI command, form submit).
// It talks to the model to read/change state, then picks the right view to render.
// It contains no business logic and no formatting — it only orchestrates.

class PostController
{
    public function __construct(
        private PostRepository $repository,
        private PostView $view
    ) {}

    public function index(): void
    {
        echo "[PostController] Handling: list all posts\n";
        $this->view->renderList($this->repository->findAll(), 'All Posts');
    }

    public function drafts(): void
    {
        echo "[PostController] Handling: list draft posts\n";
        $this->view->renderList($this->repository->findByStatus('draft'), 'Draft Posts');
    }

    public function show(int $id): void
    {
        echo "[PostController] Handling: show post #{$id}\n";
        $post = $this->repository->findById($id);
        if ($post === null) {
            $this->view->renderError("Post #{$id} not found.");

            return;
        }
        $this->view->renderPost($post);
    }

    public function publish(int $id): void
    {
        echo "[PostController] Handling: publish post #{$id}\n";
        $post = $this->repository->findById($id);
        if ($post === null) {
            $this->view->renderError("Post #{$id} not found.");

            return;
        }
        try {
            $post->publish(); // business rule enforced by the model, not the controller
            $this->view->renderPublishSuccess($post);
        } catch (\LogicException $e) {
            $this->view->renderError($e->getMessage());
        }
    }
}
