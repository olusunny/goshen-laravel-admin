<?php

namespace App\Http\Middleware;

use App\Models\MobileUser;
use App\Models\VisitorMetric;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitorMetric
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            $mobileUser = $this->mobileUser($request);

            VisitorMetric::create([
                'mobile_user_id' => $mobileUser?->id,
                'session_key' => $request->hasSession() ? $request->session()->getId() : null,
                'ip_hash' => $request->ip() ? hash('sha256', $request->ip().config('app.key')) : null,
                'path' => '/'.ltrim($request->path(), '/'),
                'endpoint' => $request->route()?->getName() ?? $request->path(),
                'channel' => str_starts_with($request->path(), 'api/') ? 'api' : 'web',
                'country' => $mobileUser?->country_of_residence ?: ($this->header($request, ['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-App-Country', 'X-Country']) ?? ($request->ip() === '127.0.0.1' ? 'Local' : 'Unknown')),
                'region' => $this->header($request, ['X-App-Region', 'X-Region']),
                'city' => $this->header($request, ['X-App-City', 'X-City']),
                'content_type' => $this->contentType($request),
                'content_id' => $this->contentId($request),
                'consumptions' => $this->isConsumption($request) ? 1 : 0,
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
                'visited_at' => now(),
            ]);
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        return $response->isSuccessful()
            && ! $request->is('admin*')
            && ! $request->is('livewire*')
            && ! $request->is('build*')
            && ! $request->is('css*')
            && ! $request->is('js*')
            && ! $request->is('storage*')
            && ! $request->is('up');
    }

    private function header(Request $request, array $names): ?string
    {
        foreach ($names as $name) {
            if (filled($request->header($name))) {
                return $request->header($name);
            }
        }

        return null;
    }

    private function contentType(Request $request): ?string
    {
        return match (true) {
            $request->is('*fetch_media*'), $request->is('*fetch_categories_media*'), $request->is('*search*'), $request->is('*discoverTrends*') => 'media',
            $request->is('*devotionals*') => 'devotional',
            $request->is('*fetch_events*') => 'event',
            $request->is('*discoverLivestreams*') => 'stream',
            default => null,
        };
    }

    private function contentId(Request $request): ?int
    {
        $data = $request->input('data');
        $payload = is_string($data) ? json_decode($data, true) : (is_array($data) ? $data : $request->all());

        return (int) ($payload['media'] ?? $payload['media_id'] ?? $payload['id'] ?? 0) ?: null;
    }

    private function mobileUser(Request $request): ?MobileUser
    {
        $payload = $this->payload($request);
        $token = $payload['api_token'] ?? $request->bearerToken();

        if (filled($token)) {
            return MobileUser::query()
                ->where('api_token_hash', hash('sha256', $token))
                ->first();
        }

        if (! empty($payload['email'])) {
            return MobileUser::query()
                ->where('email', $payload['email'])
                ->where('is_verified', true)
                ->first();
        }

        return null;
    }

    private function payload(Request $request): array
    {
        $data = $request->input('data');

        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_array($data)) {
            return $data;
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : (is_array($decoded) ? $decoded : $request->all());
    }

    private function isConsumption(Request $request): bool
    {
        return $request->is('*fetch_media*')
            || $request->is('*fetch_categories_media*')
            || $request->is('*search*')
            || $request->is('*discoverTrends*')
            || $request->is('*update_media_total_views*');
    }
}
