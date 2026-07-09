<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityPrayerCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'source' => $this->source,
            'preset_key' => $this->preset_key,
            'is_anonymous' => (bool) ($this->is_anonymous ?? true),
            'identity' => ($this->is_anonymous ?? true) ? 'Anonymous' : ($this->mobileUser?->name ?? 'Member'),
            'avatar' => ($this->is_anonymous ?? true) ? null : MediaUrl::resolve($this->mobileUser?->avatar),
            'audio_url' => $this->audio_path ? url('api/prayer-community/comments/'.$this->id.'/audio') : null,
            'audio_duration_seconds' => $this->audio_duration_seconds,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
