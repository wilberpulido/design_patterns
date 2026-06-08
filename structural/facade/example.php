<?php

/*
 * LARAVEL NOTE
 * ============
 * How Laravel uses the Facade pattern:
 * Laravel has its own "Facade" system (Illuminate\Support\Facades) which serves as
 * static proxies to services bound in the IoC container. For example:
 *   - Storage::put('file.txt', $content)  → proxies to FilesystemManager
 *   - Mail::send(...)                      → proxies to MailManager
 *   - DB::table('users')->get()            → proxies to DatabaseManager
 * This is a variant of the GoF Facade: instead of wrapping multiple subsystems into one workflow,
 * each Laravel Facade wraps one service — but the intent is the same: hide complexity behind
 * a simple, clean interface.
 *
 * Where to apply it yourself in Laravel:
 * 1. Complex multi-step workflows (e.g., user onboarding: create account, send welcome email,
 *    assign default permissions, log analytics event). Wrap it in an OnboardingFacade or
 *    a dedicated Action class so controllers stay clean.
 * 2. Third-party integrations: wrap the complexity of an external API (auth, retries,
 *    response mapping) in a Facade so the rest of the app doesn't know those details.
 * 3. Queued jobs or Artisan commands that coordinate multiple services — extract the
 *    coordination into a Facade and let the job just call one method.
 */

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

// Subsystem: generates a thumbnail from a video frame
class ThumbnailGenerator
{
    public function generate(string $filePath, int $atSecond): string
    {
        echo "[ThumbnailGenerator] Extracting frame at {$atSecond}s...\n";
        return "/tmp/thumbnail.jpg";
    }
}

// Subsystem: uploads files to the CDN and returns public URLs
class CdnUploader
{
    public function upload(string $filePath, string $destinationKey): string
    {
        echo "[CdnUploader] Uploading {$filePath} → {$destinationKey}...\n";
        return "https://cdn.example.com/{$destinationKey}";
    }
}

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

// Subsystem: notifies channel subscribers of new content
class SubscriberNotifier
{
    public function notify(string $channelId, string $title, string $videoUrl): void
    {
        echo "[SubscriberNotifier] Notifying subscribers of channel {$channelId} → \"{$title}\"\n";
    }
}

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

// Bootstrap
$publisher = new VideoPublisher(
    new VideoTranscoder(),
    new ThumbnailGenerator(),
    new CdnUploader(),
    new VideoMetadataRepository(),
    new SubscriberNotifier()
);

$dashboard = new ContentCreatorDashboard($publisher);
$dashboard->submitVideo('/uploads/raw_tutorial.mp4', 'Design Patterns Explained', 'channel_42');
