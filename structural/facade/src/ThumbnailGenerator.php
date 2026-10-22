<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Facade;

// Subsystem: generates a thumbnail from a video frame
class ThumbnailGenerator
{
    public function generate(string $filePath, int $atSecond): string
    {
        echo "[ThumbnailGenerator] Extracting frame at {$atSecond}s...\n";
        return "/tmp/thumbnail.jpg";
    }
}
