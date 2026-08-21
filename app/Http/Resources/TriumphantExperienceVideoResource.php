<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TriumphantExperienceVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $youtubeVideoId = $this->youtube_video_id;
        $playbackVisible = $this->isFeedVisible();

        return [
            'uuid' => $this->uuid,
            'event_label' => $this->event?->name,
            'caption' => $this->caption,
            'display_label' => $this->displayLabel(),
            'is_anonymous' => (bool) $this->is_anonymous,
            'duration_seconds' => (float) $this->duration_seconds,
            'youtube_video_id' => $this->when($playbackVisible, $youtubeVideoId),
            'youtube_url' => $this->when($playbackVisible && $youtubeVideoId, "https://www.youtube.com/watch?v={$youtubeVideoId}"),
            'youtube_thumbnail_url' => $this->when($playbackVisible && $youtubeVideoId, $this->youtube_thumbnail_url ?: "https://i.ytimg.com/vi/{$youtubeVideoId}/hqdefault.jpg"),
            'youtube_privacy_status' => $this->when($playbackVisible, $this->youtube_privacy_status),
            'upload_status' => $this->upload_status?->value,
            'moderation_status' => $this->moderation_status?->value,
            'quota_resume_at' => $this->quota_resume_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
