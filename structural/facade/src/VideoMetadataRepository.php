<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Facade;

// Subsystem: persists video metadata to the database
class VideoMetadataRepository
{
    public function save(array $metadata): string
    {
        $videoId = uniqid('vid_');
        echo "[VideoMetadataRepository] Saving metadata for \"{$metadata['title']}\" → ID: {$videoId}\n";
        return $videoId;
    }
}
