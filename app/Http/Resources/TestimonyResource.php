<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestimonyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $anonymous = (bool) $this->is_anonymous;
        $country = $this->mobileUser?->country_of_residence;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audio_url' => $this->audio_path ? url('api/testimonies/'.$this->id.'/audio') : null,
            'audio_duration_seconds' => $this->audio_duration_seconds,
            'is_anonymous' => $anonymous,
            'identity' => $anonymous ? 'Anonymous' : ($this->mobileUser?->name ?? 'Member'),
            'avatar' => $anonymous ? null : MediaUrl::resolve($this->mobileUser?->avatar),
            'country_of_residence' => $anonymous ? null : $country,
            'country_flag' => $anonymous ? null : self::countryFlag($country),
            'status' => $this->when($request->user()?->getTable() === 'users', $this->status),
            'admin_user' => $this->when(
                $request->user()?->getTable() === 'users',
                fn () => $this->mobileUser?->only(['id', 'name', 'email', 'country_of_residence'])
            ),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private static function countryFlag(?string $country): ?string
    {
        if (blank($country)) {
            return null;
        }

        $normalized = str($country)->lower()->trim()->toString();
        $map = [
            'nigeria' => 'NG',
            'united states' => 'US',
            'united states of america' => 'US',
            'usa' => 'US',
            'united kingdom' => 'GB',
            'uk' => 'GB',
            'canada' => 'CA',
            'germany' => 'DE',
            'france' => 'FR',
            'italy' => 'IT',
            'spain' => 'ES',
            'ghana' => 'GH',
            'south africa' => 'ZA',
            'ireland' => 'IE',
            'australia' => 'AU',
            'netherlands' => 'NL',
        ];

        $code = strlen($normalized) === 2 ? strtoupper($normalized) : ($map[$normalized] ?? null);
        if (! $code) {
            return null;
        }

        return mb_chr(127397 + ord($code[0])).mb_chr(127397 + ord($code[1]));
    }
}
