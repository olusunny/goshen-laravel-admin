<?php

return [
    'enabled' => (bool) env('GOSHEN_EXPERIENCE_ENABLED', false),
    'source_disk' => env('GOSHEN_EXPERIENCE_SOURCE_DISK', 'local'),
    'source_path' => trim((string) env('GOSHEN_EXPERIENCE_SOURCE_PATH', 'goshen/experience/shorts/source'), '/'),
    'max_bytes' => (int) env('GOSHEN_EXPERIENCE_MAX_VIDEO_BYTES', 100 * 1024 * 1024),
    'max_duration_seconds' => (int) env('GOSHEN_EXPERIENCE_MAX_VIDEO_DURATION_SECONDS', 60),
    'ffprobe_binary' => env('GOSHEN_EXPERIENCE_FFPROBE_BINARY', 'ffprobe'),
    'allowed_mime_types' => ['video/mp4', 'video/quicktime'],
    'portrait_min_ratio' => (float) env('GOSHEN_EXPERIENCE_PORTRAIT_MIN_RATIO', 0.50),
    'portrait_max_ratio' => (float) env('GOSHEN_EXPERIENCE_PORTRAIT_MAX_RATIO', 0.75),
    'release_version' => env('GOSHEN_EXPERIENCE_RELEASE_VERSION', 'triumphant-experience-v1'),
    'failed_source_retention_days' => (int) env('GOSHEN_EXPERIENCE_FAILED_SOURCE_RETENTION_DAYS', 30),

    'youtube' => [
        'client_id' => env('YOUTUBE_OAUTH_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('YOUTUBE_OAUTH_REDIRECT_URI', rtrim((string) env('APP_URL', ''), '/').'/admin/goshen-youtube/callback'),
        'state_ttl_seconds' => (int) env('YOUTUBE_OAUTH_STATE_TTL_SECONDS', 600),
        'default_privacy' => env('YOUTUBE_DEFAULT_PRIVACY', 'private'),
        'upload_queue' => env('YOUTUBE_UPLOAD_QUEUE', 'youtube-uploads'),
        'chunk_bytes' => (int) env('YOUTUBE_UPLOAD_CHUNK_BYTES', 5 * 1024 * 1024),
        'slice_seconds' => (int) env('YOUTUBE_UPLOAD_SLICE_SECONDS', 40),
        'fifo_pacing_seconds' => (int) env('YOUTUBE_FIFO_PACING_SECONDS', 5),
        'http_timeout_seconds' => (int) env('YOUTUBE_HTTP_TIMEOUT_SECONDS', 30),
        'scopes' => [
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
        ],
    ],
];
