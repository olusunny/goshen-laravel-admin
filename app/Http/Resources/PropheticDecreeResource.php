<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropheticDecreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => 'Prophetic Decree',
            'title' => $this->title,
            'audio_url' => $this->audio_path ? url('api/prayer-community/prophetic-decree/'.$this->id.'/audio') : null,
            'duration' => $this->duration,
            'mime_type' => $this->mime_type,
            'go' => [
                'id' => $this->goUser?->id,
                'name' => $this->goUser?->name ?? 'G.O',
                'profile_image' => MediaUrl::resolve($this->goUser?->avatar),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
