<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Facade;

// Subsystem: transcodes video to multiple resolutions
class VideoTranscoder
{
    public function transcode(string $filePath, array $resolutions): array
    {
        $outputs = [];
        foreach ($resolutions as $res) {
            echo "[VideoTranscoder] Transcoding to {$res}p...\n";
            $outputs[$res] = "/tmp/encoded_{$res}p.mp4";
        }
        return $outputs;
    }
}
