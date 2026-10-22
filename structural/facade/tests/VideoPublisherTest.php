<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Structural\Facade;

use DesignPatterns\Structural\Facade\CdnUploader;
use DesignPatterns\Structural\Facade\SubscriberNotifier;
use DesignPatterns\Structural\Facade\ThumbnailGenerator;
use DesignPatterns\Structural\Facade\VideoMetadataRepository;
use DesignPatterns\Structural\Facade\VideoPublisher;
use DesignPatterns\Structural\Facade\VideoTranscoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VideoPublisher::class)]
final class VideoPublisherTest extends TestCase
{
    private SubscriberNotifier $notifier;

    private function publisher(): VideoPublisher
    {
        // Spy on the last subsystem so we can check the facade ran the whole pipeline.
        $this->notifier = new class extends SubscriberNotifier
        {
            public int $calls = 0;

            public function notify(string $channelId, string $title, string $videoUrl): void
            {
                $this->calls++;
            }
        };

        return new VideoPublisher(
            new VideoTranscoder,
            new ThumbnailGenerator,
            new CdnUploader,
            new VideoMetadataRepository,
            $this->notifier,
        );
    }

    public function test_one_call_runs_the_whole_subsystem_pipeline(): void
    {
        $this->expectOutputRegex('/Transcoding to 1080p.*Pipeline complete/s');

        $videoId = $this->publisher()->publish('/tmp/raw.mov', 'Facade 101', 'channel_1');

        $this->assertNotEmpty($videoId);
        $this->assertSame(1, $this->notifier->calls);
    }

    public function test_draft_is_a_simpler_entry_point_that_skips_notification(): void
    {
        $this->expectOutputRegex('/Transcoding to 720p/');

        $this->publisher()->publishDraft('/tmp/raw.mov', 'Work in progress', 'channel_1');

        $this->assertSame(0, $this->notifier->calls);
    }
}
