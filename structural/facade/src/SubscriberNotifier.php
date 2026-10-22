<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Facade;

// Subsystem: notifies channel subscribers of new content
class SubscriberNotifier
{
    public function notify(string $channelId, string $title, string $videoUrl): void
    {
        echo "[SubscriberNotifier] Notifying subscribers of channel {$channelId} → \"{$title}\"\n";
    }
}
