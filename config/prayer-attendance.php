<?php

return [
    'middleware' => [
        'api' => [
            'api',
            \App\Http\Middleware\AuthenticateMobileApiToken::class,
            \ChurchTools\GoshenPrayerAttendance\Http\Middleware\EnsurePrayerAttendanceActive::class,
        ],
        'admin' => [
            'web',
            'auth',
            \ChurchTools\GoshenPrayerAttendance\Http\Middleware\EnsurePrayerAttendanceActive::class,
        ],
    ],
];
