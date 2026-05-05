<?php

/**
 * Laravel note:
 *
 * How Laravel applies MVC natively:
 * - Models:      Eloquent classes in app/Models/ represent database tables and
 *                encapsulate business rules (relationships, scopes, accessors).
 * - Views:       Blade templates in resources/views/ handle all rendering.
 *                Components and layouts keep the presentation layer composable.
 * - Controllers: Classes in app/Http/Controllers/ receive HTTP requests via routes,
 *                query models, and return views or JSON responses.
 *                Route-model binding automatically resolves model instances.
 *
 * Where it makes sense to apply MVC yourself in a Laravel project:
 * - Console commands that display formatted reports: use a dedicated View class
 *   instead of echoing directly inside the command (command acts as Controller).
 * - Admin panels with complex multi-step workflows: one controller per workflow step
 *   (e.g. OrderApprovalController) keeps each step isolated and testable.
 * - API versioning: separate transformer/resource classes per version so the
 *   Model is never coupled to a specific response shape.
 */

// ─── Model ────────────────────────────────────────────────────────────────────
// The model owns data and business rules.
// It knows nothing about how its data will be displayed.

class Post
{
    private string $status;

    public function __construct(
        public readonly int    $id,
        public readonly string $author,
        public string          $title,
        public string          $content,
        string                 $status = 'draft'
    ) {
        $this->status = $status;
    }

    // Business rule lives in the model, not in the controller.
    // The controller cannot bypass this rule by accident.
    public function publish(): void
    {
        if ($this->status === 'published') {
            throw new \LogicException("Post #{$this->id} is already published.");
        }
        $this->status = 'published';
    }

    public function getStatus(): string { return $this->status; }
    public function isDraft(): bool     { return $this->status === 'draft'; }
}

// matiz: separating storage from the domain object (Post) lets you swap
// the data source (array → MySQL → Redis) without touching Post or PostController.
// This is the Repository sub-pattern working inside the Model layer.
class PostRepository
{
    /** @var Post[] */
    private array $posts   = [];
    private int   $nextId  = 1;

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
    public function findAll(): array { return array_values($this->posts); }

    /** @return Post[] */
    public function findByStatus(string $status): array
    {
        return array_values(
            array_filter($this->posts, fn(Post $p) => $p->getStatus() === $status)
        );
    }
}

// ─── View ─────────────────────────────────────────────────────────────────────
// The view only formats and prints the data it receives.
// It never queries the model directly — the controller decides what to pass.

class PostView
{
    public function renderList(array $posts, string $title): void
    {
        echo "\n┌─ {$title} (" . count($posts) . " post(s))\n";
        foreach ($posts as $post) {
            $badge = $post->isDraft() ? '[DRAFT]    ' : '[PUBLISHED]';
            echo "│  #{$post->id} {$badge} \"{$post->title}\" — by {$post->author}\n";
        }
        if (empty($posts)) {
            echo "│  (no posts)\n";
        }
        echo "└─\n";
    }

    public function renderPost(Post $post): void
    {
        echo "\n┌─ Post Detail ───────────────────────────────\n";
        echo "│  ID:      #{$post->id}\n";
        echo "│  Title:   {$post->title}\n";
        echo "│  Author:  {$post->author}\n";
        echo "│  Status:  {$post->getStatus()}\n";
        echo "│  Content: {$post->content}\n";
        echo "└─────────────────────────────────────────────\n";
    }

    public function renderPublishSuccess(Post $post): void
    {
        echo "[PostView] Post #{$post->id} \"{$post->title}\" is now live.\n";
    }

    public function renderError(string $message): void
    {
        echo "[PostView] ERROR: {$message}\n";
    }
}

// ─── Controller ───────────────────────────────────────────────────────────────
// The controller handles user intent (HTTP request, CLI command, form submit).
// It talks to the model to read/change state, then picks the right view to render.
// It contains no business logic and no formatting — it only orchestrates.

class PostController
{
    public function __construct(
        private PostRepository $repository,
        private PostView       $view
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

// ─── Bootstrap ────────────────────────────────────────────────────────────────

echo "=== Blog CMS — MVC Pattern ===\n";

$repository = new PostRepository();
$view       = new PostView();
$controller = new PostController($repository, $view);

// Seed posts (in a real app this comes from the database)
$repository->create('alice', 'Getting Started with MVC', 'MVC separates concerns into three layers...');
$repository->create('bob',   'PHP 8 Features',           'Fibers, enums, named arguments...');
$repository->create('alice', 'Deploying to Production',  'Use Docker for consistent environments...');

// Simulate user actions (in a real app these come from HTTP routes)
$controller->index();
$controller->drafts();
$controller->show(2);
$controller->publish(1);
$controller->publish(1); // already published — the model enforces the rule
$controller->show(99);   // not found
