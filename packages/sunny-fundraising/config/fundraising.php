<?php

return [
    'enabled' => env('FUNDRAISING_ENABLED', true),

    'route_prefix' => env('FUNDRAISING_ROUTE_PREFIX', 'fundraising'),
    'api_prefix' => env('FUNDRAISING_API_PREFIX', 'api/fundraising'),
    'admin_prefix' => env('FUNDRAISING_ADMIN_PREFIX', 'admin/fundraising'),
    'admin_timezone' => env('FUNDRAISING_ADMIN_TIMEZONE', 'Europe/London'),

    'middleware' => [
        'web' => ['web'],
        'api' => ['api'],
        'admin' => ['web', 'auth'],
    ],

    'models' => [
        'user' => App\Models\MobileUser::class,
    ],

    'wallet' => [
        'enabled' => env('FUNDRAISING_WALLET_ENABLED', true),
        'gateway' => App\Services\FundraisingWalletGateway::class,
        'currency' => env('FUNDRAISING_CURRENCY', 'GBP'),
        'minimum_contribution' => (float) env('FUNDRAISING_MIN_CONTRIBUTION', 1),
    ],

    'payments' => [
        'stripe' => [
            'enabled' => env('FUNDRAISING_STRIPE_ENABLED', true),
            'success_url' => env('FUNDRAISING_STRIPE_SUCCESS_URL', ''),
            'cancel_url' => env('FUNDRAISING_STRIPE_CANCEL_URL', ''),
        ],
    ],

    'media' => [
        'disk' => env('FUNDRAISING_MEDIA_DISK', 'public'),
        'path' => env('FUNDRAISING_MEDIA_PATH', 'fundraising'),
        'max_image_size_kb' => 5120,
        'max_video_size_kb' => 102400,
        'max_audio_size_kb' => 20480,
        'allowed_image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_video_mimes' => ['mp4', 'mov', 'webm'],
        'allowed_audio_mimes' => ['mp3', 'wav', 'm4a', 'aac'],
    ],

    'features' => [
        'web_pages' => true,
        'api' => true,
        'admin_pages' => true,
        'audio_message' => true,
        'video_upload' => true,
        'youtube_link' => true,
        'multiple_images' => true,
        'anonymous_donors' => false,
        'public_recent_donors' => true,
    ],

    'campaigns' => [
        'allow_multiple_active' => false,
        'show_completed_campaigns' => false,
        'auto_close_expired_campaigns' => true,
    ],
];
