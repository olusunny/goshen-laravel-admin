<?php

namespace App\Services\YouTube;

use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;

class GoogleYouTubeGateway implements YouTubeGateway
{
    public function __construct(
        private readonly GoogleYouTubeTokenProvider $tokens,
        private readonly YouTubeGatewayFailureClassifier $failures,
    ) {}

    public function currentChannel(string $accessToken): YouTubeChannel
    {
        $response = $this->request('GET', 'https://www.googleapis.com/youtube/v3/channels', [
            'headers' => $this->bearerHeaders($accessToken),
            'query' => ['part' => 'snippet', 'mine' => 'true'],
        ]);

        $item = $response['items'][0] ?? null;
        if (! is_array($item) || blank($item['id'] ?? null)) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_channel_not_found');
        }

        return new YouTubeChannel((string) $item['id'], (string) data_get($item, 'snippet.title', 'YouTube channel'));
    }

    public function startResumableUpload(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): string
    {
        $accessToken = $this->tokens->accessToken($connection);
        $size = $this->sourceSize($video);

        $response = $this->request('POST', 'https://www.googleapis.com/upload/youtube/v3/videos', [
            'headers' => array_merge($this->bearerHeaders($accessToken), [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => (string) $video->mime_type,
                'X-Upload-Content-Length' => (string) $size,
            ]),
            'query' => ['uploadType' => 'resumable', 'part' => 'snippet,status'],
            'json' => [
                'snippet' => [
                    'title' => $this->videoTitle($video),
                    'description' => trim((string) $video->caption),
                    'categoryId' => '22',
                ],
                'status' => [
                    'privacyStatus' => $connection->default_privacy,
                    'selfDeclaredMadeForKids' => false,
                ],
            ],
        ], includeHeaders: true);

        $sessionUrl = (string) ($response['headers']['location'] ?? '');
        $this->assertResumableUrl($sessionUrl);

        return $sessionUrl;
    }

    public function uploadChunk(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): YouTubeUploadChunkResult
    {
        $sessionUrl = (string) $video->resumable_session_url;
        $this->assertResumableUrl($sessionUrl);

        $size = $this->sourceSize($video);
        $offset = max(0, (int) $video->uploaded_bytes);
        if ($offset >= $size) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_offset_invalid');
        }

        $chunk = $this->readChunk($video, $offset);
        $length = strlen($chunk);
        if ($length === 0) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_unreadable');
        }

        $accessToken = $this->tokens->accessToken($connection);
        $response = $this->request('PUT', $sessionUrl, [
            'headers' => array_merge($this->bearerHeaders($accessToken), [
                'Content-Length' => (string) $length,
                'Content-Type' => (string) $video->mime_type,
                'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $offset + $length - 1, $size),
            ]),
            'body' => $chunk,
        ], includeHeaders: true, allowResumeIncomplete: true);

        $youtubeId = data_get($response, 'body.id');
        if (filled($youtubeId)) {
            return new YouTubeUploadChunkResult($size, (string) $youtubeId);
        }

        $range = (string) ($response['headers']['range'] ?? '');
        if (preg_match('/bytes=0-(\d+)/', $range, $matches) === 1) {
            return new YouTubeUploadChunkResult((int) $matches[1] + 1);
        }

        return new YouTubeUploadChunkResult($offset + $length);
    }

    public function processingStatus(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): array
    {
        if (blank($video->youtube_video_id)) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_video_id_missing');
        }

        $response = $this->request('GET', 'https://www.googleapis.com/youtube/v3/videos', [
            'headers' => $this->bearerHeaders($this->tokens->accessToken($connection)),
            'query' => [
                'part' => 'status,processingDetails,snippet',
                'id' => $video->youtube_video_id,
            ],
        ]);

        $item = $response['items'][0] ?? null;
        if (! is_array($item)) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Transient, 'youtube_video_not_visible_yet');
        }

        $status = strtolower((string) data_get($item, 'processingDetails.processingStatus', 'processing'));
        $state = match ($status) {
            'succeeded' => 'succeeded',
            'failed', 'terminated' => 'failed',
            default => 'processing',
        };

        return [
            'state' => $state,
            'thumbnail_url' => data_get($item, 'snippet.thumbnails.high.url')
                ?? data_get($item, 'snippet.thumbnails.medium.url')
                ?? data_get($item, 'snippet.thumbnails.default.url'),
        ];
    }

    public function deleteVideo(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): void
    {
        if (blank($video->youtube_video_id)) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_video_id_missing');
        }

        $this->request('DELETE', 'https://www.googleapis.com/youtube/v3/videos', [
            'headers' => $this->bearerHeaders($this->tokens->accessToken($connection)),
            'query' => ['id' => $video->youtube_video_id],
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $url,
        array $options = [],
        bool $includeHeaders = false,
        bool $allowResumeIncomplete = false,
    ): array {
        try {
            $response = $this->client()->request($method, $url, array_merge($options, ['http_errors' => false]));
        } catch (GuzzleException) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Transient, 'youtube_network_error');
        }

        $body = trim((string) $response->getBody());
        $payload = $body === '' ? [] : json_decode($body, true);
        $payload = is_array($payload) ? $payload : [];
        $status = $response->getStatusCode();

        if ($allowResumeIncomplete && $status === 308) {
            return [
                'body' => $payload,
                'headers' => [
                    'range' => $response->getHeaderLine('Range'),
                ],
            ];
        }

        if ($status < 200 || $status >= 300) {
            $kind = $this->failures->classify($payload, $status);
            $retryAfter = $this->retryAfterSeconds($response->getHeaderLine('Retry-After'));

            throw new YouTubeGatewayException($kind, 'youtube_http_'.$status, $retryAfter);
        }

        if (! $includeHeaders) {
            return $payload;
        }

        return [
            'body' => $payload,
            'headers' => [
                'location' => $response->getHeaderLine('Location'),
                'range' => $response->getHeaderLine('Range'),
            ],
        ];
    }

    private function sourceSize(GoshenExperienceVideo $video): int
    {
        $this->assertPrivateSource($video);
        try {
            $size = Storage::disk($video->local_disk)->size($video->local_path);
        } catch (\Throwable) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_missing');
        }

        if (! is_int($size) || $size < 1) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_missing');
        }

        return $size;
    }

    private function readChunk(GoshenExperienceVideo $video, int $offset): string
    {
        $this->assertPrivateSource($video);
        try {
            $path = Storage::disk($video->local_disk)->path($video->local_path);
        } catch (\Throwable) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_unreadable');
        }
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_unreadable');
        }

        try {
            if (fseek($handle, $offset) !== 0) {
                throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_unreadable');
            }

            $chunk = fread($handle, (int) config('goshen_experience.youtube.chunk_bytes'));
            if ($chunk === false) {
                throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_unreadable');
            }

            return $chunk;
        } finally {
            fclose($handle);
        }
    }

    private function assertPrivateSource(GoshenExperienceVideo $video): void
    {
        $expectedDisk = (string) config('goshen_experience.source_disk');
        $expectedPrefix = $this->normalizeRelativePath((string) config('goshen_experience.source_path'));
        $path = $this->normalizeRelativePath((string) $video->local_path);

        if (
            $video->local_disk !== $expectedDisk
            || $expectedPrefix === null
            || $path === null
            || ! str_starts_with($path, $expectedPrefix.'/')
        ) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_upload_source_path_invalid');
        }
    }

    private function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        $segments = explode('/', $path);
        if (collect($segments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..')) {
            return null;
        }

        return implode('/', $segments);
    }

    private function assertResumableUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || ! in_array($host, ['www.googleapis.com', 'youtube.googleapis.com'], true)) {
            throw new YouTubeGatewayException(YouTubeGatewayFailureKind::Permanent, 'youtube_resumable_session_invalid');
        }
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(string $accessToken): array
    {
        return ['Authorization' => 'Bearer '.$accessToken];
    }

    private function videoTitle(GoshenExperienceVideo $video): string
    {
        return mb_substr(trim('Triumphant Experience - '.($video->display_name ?: 'MFM attendee')), 0, 100);
    }

    private function retryAfterSeconds(string $header): ?int
    {
        return ctype_digit($header) ? max(1, (int) $header) : null;
    }

    private function client(): Client
    {
        return new Client([
            'connect_timeout' => min(10, (int) config('goshen_experience.youtube.http_timeout_seconds')),
            'timeout' => (int) config('goshen_experience.youtube.http_timeout_seconds'),
        ]);
    }
}
