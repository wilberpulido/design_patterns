<?php

declare(strict_types=1);

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

// ─── Pattern map ──────────────────────────────────────────────────────────────
// This file only runs the demo. Read the classes in src/ in this order:
//   src/VideoPublisher.php         Facade — one simple method that orchestrates the whole pipeline
//   src/VideoTranscoder.php        Subsystems — complex pieces the client no longer talks to directly
//   src/ThumbnailGenerator.php
//   src/CdnUploader.php
//   src/VideoMetadataRepository.php
//   src/SubscriberNotifier.php
//   src/ContentCreatorDashboard.php Client — only knows the facade
// Executable guarantees of the pattern: tests/VideoPublisherTest.php

require __DIR__ . '/../../vendor/autoload.php';

use DesignPatterns\Structural\Facade\CdnUploader;
use DesignPatterns\Structural\Facade\ContentCreatorDashboard;
use DesignPatterns\Structural\Facade\SubscriberNotifier;
use DesignPatterns\Structural\Facade\ThumbnailGenerator;
use DesignPatterns\Structural\Facade\VideoMetadataRepository;
use DesignPatterns\Structural\Facade\VideoPublisher;
use DesignPatterns\Structural\Facade\VideoTranscoder;

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
