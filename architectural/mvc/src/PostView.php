<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Mvc;

// ─── View ─────────────────────────────────────────────────────────────────────
// The view only formats and prints the data it receives.
// It never queries the model directly — the controller decides what to pass.

class PostView
{
    public function renderList(array $posts, string $title): void
    {
        echo "\n┌─ {$title} (".count($posts)." post(s))\n";
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
