<?php

declare(strict_types=1);

namespace DesignPatterns\Structural\Facade;

// The Facade — hides the entire video publishing pipeline behind one method.
// The client has no idea about encoding, CDN uploads, or push notifications.
class VideoPublisher
{
    public function __construct(
        private VideoTranscoder $transcoder,
        private ThumbnailGenerator $thumbnailGenerator,
        private CdnUploader $cdn,
        private VideoMetadataRepository $repository,
        private SubscriberNotifier $notifier
    ) {}

    public function publish(string $rawFilePath, string $title, string $channelId): string
    {
        echo "\n[VideoPublisher] Starting publish pipeline for \"{$title}\"...\n";

        $encodedFiles = $this->transcoder->transcode($rawFilePath, [360, 720, 1080]);
        $thumbnail    = $this->thumbnailGenerator->generate($rawFilePath, 5);

        $cdnUrls = [];
        foreach ($encodedFiles as $res => $file) {
            $cdnUrls[$res] = $this->cdn->upload($file, "videos/{$channelId}/{$res}p.mp4");
        }
        $thumbnailUrl = $this->cdn->upload($thumbnail, "thumbnails/{$channelId}/thumb.jpg");

        $videoId = $this->repository->save([
            'title'         => $title,
            'channel_id'    => $channelId,
            'cdn_urls'      => $cdnUrls,
            'thumbnail_url' => $thumbnailUrl,
        ]);

        $this->notifier->notify($channelId, $title, $cdnUrls[720]);

        echo "[VideoPublisher] Pipeline complete. Video ID: {$videoId}\n";
        return $videoId;
    }

    // matiz: a Facade can expose multiple methods at different levels of control.
    // publishDraft() runs the same pipeline but skips subscriber notification,
    // giving creators a way to review content before going live — without ever
    // touching the subsystems directly. The facade provides simplicity by default
    // and opt-in control when needed.
    public function publishDraft(string $rawFilePath, string $title, string $channelId): string
    {
        echo "\n[VideoPublisher] Publishing as DRAFT — subscriber notification skipped...\n";
        $this->transcoder->transcode($rawFilePath, [720]);
        $videoId = $this->repository->save(['title' => $title, 'channel_id' => $channelId, 'status' => 'draft']);
        echo "[VideoPublisher] Draft saved with ID: {$videoId}. Awaiting creator review.\n";
        return $videoId;
    }
}
