<?php

return [
    'enabled' => env('CHURCH_BIRTHDAY_CELEBRATIONS_ENABLED', true),
    'package_key' => 'church_birthday_celebrations',
    'capability' => 'church_birthday_celebrations',
    'api_prefix' => env('CHURCH_BIRTHDAY_CELEBRATIONS_API_PREFIX', 'api/v1/church-birthday-celebrations'),
    'admin_prefix' => env('CHURCH_BIRTHDAY_CELEBRATIONS_ADMIN_PREFIX', 'admin/church-birthday-celebrations'),
    'timezone' => env('CHURCH_BIRTHDAY_CELEBRATIONS_TIMEZONE', 'Africa/Lagos'),
    'preview_days' => 7,
    'retention_days' => 30,
    'upcoming_days' => 14,
    'greeting_max_length' => 280,
    'report_threshold' => 3,
    'notification_max_attempts' => 5,
    'feb_29_policy' => env('CHURCH_BIRTHDAY_CELEBRATIONS_FEB_29_POLICY', 'february_28'),
    'reactions' => ['love', 'pray', 'celebrate'],
    'media' => [
        'disk' => env('CHURCH_BIRTHDAY_CELEBRATIONS_DISK', 'local'),
        'path' => 'church-birthday-celebrations/cards',
        'max_bytes' => 5 * 1024 * 1024,
        'max_width' => 4096,
        'max_height' => 4096,
        'max_pixels' => 16_000_000,
    ],
    'models' => ['mobile_user' => App\Models\MobileUser::class],
    'middleware' => [
        'api' => ['api', ChurchTools\ChurchBirthdayCelebrations\Http\Middleware\AuthenticateBirthdayRequester::class, ChurchTools\ChurchBirthdayCelebrations\Http\Middleware\EnsureBirthdayCelebrationsActive::class],
        'admin' => ['web', 'auth', ChurchTools\ChurchBirthdayCelebrations\Http\Middleware\EnsureBirthdayCelebrationsActive::class],
    ],
    'permissions' => [
        'manage' => 'church_birthday_celebrations.manage',
        'moderate' => 'church_birthday_celebrations.moderate',
        'recover' => 'church_birthday_celebrations.recover',
    ],
];
