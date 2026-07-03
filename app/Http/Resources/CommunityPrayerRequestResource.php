<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityPrayerRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'text' => $this->text,
            'audio_url' => $this->audio_path ? url('api/prayer-community/'.$this->id.'/audio') : null,
            'audio_duration_seconds' => $this->audio_duration_seconds,
            'is_anonymous' => (bool) ($this->is_anonymous ?? true),
            'owner_id' => $this->mobile_user_id,
            'can_comment' => $request->user()?->getTable() === 'mobile_users'
                ? $request->user()->id !== $this->mobile_user_id
                : true,
            'identity' => ($this->is_anonymous ?? true) ? 'Anonymous' : ($this->mobileUser?->name ?? 'Member'),
            'avatar' => ($this->is_anonymous ?? true) ? null : MediaUrl::resolve($this->mobileUser?->avatar),
            'admin_user' => $this->when(
                $request->user()?->getTable() === 'users',
                fn () => $this->mobileUser?->only(['id', 'name', 'email'])
            ),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'flags_count' => $this->flags_count,
            'comments_count' => $this->comments_count,
            'hidden_at' => $this->when($request->user()?->getTable() === 'users', $this->hidden_at?->toIso8601String()),
            'hidden_reason' => $this->when($request->user()?->getTable() === 'users', $this->hidden_reason),
            'created_at' => $this->created_at?->toIso8601String(),
            'comments' => CommunityPrayerCommentResource::collection($this->whenLoaded('comments')),
            'suggestions' => CommunityPrayerCommentSuggestionResource::collection($this->whenLoaded('suggestions')),
        ];
    }
}
