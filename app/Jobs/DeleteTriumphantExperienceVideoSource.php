<?php

namespace App\Jobs;

use App\Models\GoshenExperienceVideo;
use App\Services\GoshenExperienceVideoStateMachine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteTriumphantExperienceVideoSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $videoId) {}

    public function handle(GoshenExperienceVideoStateMachine $stateMachine): void
    {
        $video = GoshenExperienceVideo::query()->find($this->videoId);
        if (! $video || ! $stateMachine->canDeleteLocalSource($video)) {
            return;
        }

        $disk = Storage::disk($video->local_disk);
        if (! $disk->delete($video->local_path)) {
            throw new RuntimeException('Triumphant Experience source cleanup could not complete.');
        }

        $video->forceFill([
            'local_deleted_at' => now(),
            'local_path' => null,
        ])->save();
    }
}
