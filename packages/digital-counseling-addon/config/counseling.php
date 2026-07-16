<?php

return [
    'enabled' => env('COUNSELING_ENABLED', false),

    'api_prefix' => env('COUNSELING_API_PREFIX', 'api/v1/counseling'),
    'admin_prefix' => env('COUNSELING_ADMIN_PREFIX', 'admin/counseling'),
    'route_prefix' => env('COUNSELING_WEB_PREFIX', 'counseling'),

    'middleware' => [
        'api' => ['api', 'auth:sanctum'],
        'admin' => ['web', 'auth'],
        'web' => ['web', 'auth'],
    ],

    'features' => [
        'api' => true,
        'admin_pages' => false,
        'web_pages' => false,
        'voice_notes' => true,
        'country_resources' => true,
        'safeguarding' => true,
        'minors' => false,
    ],

    'models' => [
        'requester' => App\Models\MobileUser::class,
        'admin_user' => App\Models\User::class,
    ],

    'permissions' => [
        'request' => 'counseling.request',
        'triage' => 'counseling.triage',
        'assign' => 'counseling.assign',
        'respond' => 'counseling.respond',
        'safeguarding' => 'counseling.safeguarding',
        'settings' => 'counseling.settings',
        'break_glass' => 'counseling.break-glass',
    ],

    'media' => [
        'disk' => env('COUNSELING_MEDIA_DISK', 'local'),
        'path' => env('COUNSELING_MEDIA_PATH', 'counseling/voice-notes'),
        'max_audio_size_kb' => (int) env('COUNSELING_MAX_AUDIO_SIZE_KB', 20480),
        'max_audio_duration_seconds' => (int) env('COUNSELING_MAX_AUDIO_DURATION_SECONDS', 300),
        'allowed_audio_mimetypes' => [
            'audio/mpeg',
            'audio/mp4',
            'audio/aac',
            'audio/wav',
            'audio/x-wav',
            'audio/webm',
            'audio/ogg',
        ],
    ],

    'case' => [
        'default_country_code' => env('COUNSELING_DEFAULT_COUNTRY_CODE'),
        'default_locale' => env('COUNSELING_DEFAULT_LOCALE', 'en'),
        'default_timezone' => env('COUNSELING_DEFAULT_TIMEZONE', 'UTC'),
        'allow_requester_close' => true,
    ],
];
