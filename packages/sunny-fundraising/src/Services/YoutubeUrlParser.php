<?php

namespace Sunny\Fundraising\Services;

class YoutubeUrlParser
{
    public function videoId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (isset($query['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $query['v'])) {
            return (string) $query['v'];
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $candidate = collect(explode('/', $path))->last();

        return is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate)
            ? $candidate
            : null;
    }
}
