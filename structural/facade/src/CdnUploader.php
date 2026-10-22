<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Facade;

// Subsystem: uploads files to the CDN and returns public URLs
class CdnUploader
{
    public function upload(string $filePath, string $destinationKey): string
    {
        echo "[CdnUploader] Uploading {$filePath} → {$destinationKey}...\n";

        return "https://cdn.example.com/{$destinationKey}";
    }
}
