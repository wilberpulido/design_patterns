<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Mvc;

// ─── Model ────────────────────────────────────────────────────────────────────
// The model owns data and business rules.
// It knows nothing about how its data will be displayed.

class Post
{
    private string $status;

    public function __construct(
        public readonly int $id,
        public readonly string $author,
        public string $title,
        public string $content,
        string $status = 'draft'
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
