<?php

namespace App\Jobs;

use App\Models\CommunityPrayerRequest;
use App\Models\PropheticDecree;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DeleteExpiredCommunityPrayerRequests implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        CommunityPrayerRequest::query()
            ->expired()
            ->chunkById(100, function ($requests) {
                foreach ($requests as $request) {
                    if ($request->audio_path) {
                        Storage::disk('public')->delete($request->audio_path);
                    }

                    $request->comments()->whereNotNull('audio_path')->chunkById(100, function ($comments) {
                        foreach ($comments as $comment) {
                            Storage::disk('public')->delete($comment->audio_path);
                        }
                    });

                    $request->delete();
                }
            });

        PropheticDecree::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($decrees) {
                foreach ($decrees as $decree) {
                    if ($decree->audio_path) {
                        Storage::disk('public')->delete($decree->audio_path);
                    }

                    $decree->delete();
                }
            });
    }
}
