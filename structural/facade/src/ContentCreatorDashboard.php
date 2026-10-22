<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Facade;

// Client — knows nothing about encoding, CDN, or notifications.
class ContentCreatorDashboard
{
    public function __construct(private VideoPublisher $publisher) {}

    public function submitVideo(string $filePath, string $title, string $channelId): void
    {
        echo "[ContentCreatorDashboard] Creator submitted video: \"{$title}\"\n";
        $videoId = $this->publisher->publish($filePath, $title, $channelId);
        echo "[ContentCreatorDashboard] Video is live. ID: {$videoId}\n";
    }
}
