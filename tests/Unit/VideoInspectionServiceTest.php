<?php

namespace Tests\Unit;

use App\Contracts\VideoProbe;
use App\Data\VideoInspectionResult;
use App\Services\VideoInspectionService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VideoInspectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('goshen_experience.max_duration_seconds', 60);
        config()->set('goshen_experience.allowed_mime_types', ['video/mp4']);
        config()->set('goshen_experience.portrait_min_ratio', 0.50);
        config()->set('goshen_experience.portrait_max_ratio', 0.75);
    }

    public function test_it_rejects_a_video_the_server_measures_as_more_than_sixty_seconds(): void
    {
        $service = $this->serviceFor(new VideoInspectionResult(60.001, 1080, 1920, true, true, 'mov,mp4,m4a,3gp,3g2,mj2', 'h264', 'aac'));

        $this->assertVideoValidationError(fn () => $service->inspect(__FILE__, 'video/mp4'), '60');
    }

    public function test_it_rejects_a_landscape_video_even_when_the_client_claims_it_is_portrait(): void
    {
        $service = $this->serviceFor(new VideoInspectionResult(30, 1920, 1080, true, true, 'mov,mp4,m4a,3gp,3g2,mj2', 'h264', 'aac'));

        $this->assertVideoValidationError(fn () => $service->inspect(__FILE__, 'video/mp4'), 'portrait');
    }

    public function test_it_rejects_a_file_without_an_audio_stream(): void
    {
        $service = $this->serviceFor(new VideoInspectionResult(30, 1080, 1920, true, false, 'mov,mp4,m4a,3gp,3g2,mj2', 'h264', ''));

        $this->assertVideoValidationError(fn () => $service->inspect(__FILE__, 'video/mp4'), 'audio');
    }

    public function test_it_rejects_an_unsupported_container_even_with_video_and_audio_streams(): void
    {
        $service = $this->serviceFor(new VideoInspectionResult(30, 1080, 1920, true, true, 'matroska,webm', 'h264', 'aac'));

        $this->assertVideoValidationError(fn () => $service->inspect(__FILE__, 'video/mp4'), 'MP4 or QuickTime');
    }

    private function serviceFor(VideoInspectionResult $result): VideoInspectionService
    {
        return new VideoInspectionService(new class($result) implements VideoProbe
        {
            public function __construct(private readonly VideoInspectionResult $result) {}

            public function inspect(string $absolutePath): VideoInspectionResult
            {
                return $this->result;
            }
        });
    }

    private function assertVideoValidationError(callable $callback, string $expectedMessagePart): void
    {
        try {
            $callback();
            $this->fail('Expected video validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('video', $exception->errors());
            $this->assertStringContainsString($expectedMessagePart, $exception->errors()['video'][0]);
        }
    }
}
