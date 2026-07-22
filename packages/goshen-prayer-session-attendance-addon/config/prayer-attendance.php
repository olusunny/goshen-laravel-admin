<?php

return [
    'enabled' => env('PRAYER_ATTENDANCE_ENABLED', true),
    'package_key' => 'prayer_session_attendance',
    'capability' => 'prayer_session_attendance',
    'api_prefix' => env('PRAYER_ATTENDANCE_API_PREFIX', 'api/v1/prayer-session-attendance'),
    'admin_prefix' => env('PRAYER_ATTENDANCE_ADMIN_PREFIX', 'admin/prayer-attendance'),
    'middleware' => [
        'api' => ['api', \ChurchTools\GoshenPrayerAttendance\Http\Middleware\AuthenticatePrayerAttendanceRequester::class, \ChurchTools\GoshenPrayerAttendance\Http\Middleware\EnsurePrayerAttendanceActive::class],
        'admin' => ['web', 'auth', \ChurchTools\GoshenPrayerAttendance\Http\Middleware\EnsurePrayerAttendanceActive::class],
    ],
    'models' => [
        'mobile_user' => App\Models\MobileUser::class,
        'event' => Personal\EventInstallments\Models\Event::class,
        'ticket' => Personal\EventInstallments\Models\Ticket::class,
        'attendee' => Personal\EventInstallments\Models\Attendee::class,
        'booking' => Personal\EventInstallments\Models\Booking::class,
    ],
    'permissions' => [
        'view' => 'prayer_session_attendance.view',
        'confirm' => 'prayer_session_attendance.confirm',
        'coordinate' => 'prayer_session_attendance.coordinate',
        'report' => 'prayer_session_attendance.report',
        'correct' => 'prayer_session_attendance.correct',
        'admin' => 'prayer_session_attendance.admin',
    ],
    'notification' => [
        'category' => 'events',
        'activation_title' => 'Prayer session is now open',
        'activation_body' => 'You are warmly invited to confirm your attendance for this prayer session.',
        'reminder_title' => 'A gentle prayer session reminder',
        'reminder_body' => 'When you are ready, you can confirm your attendance for the active prayer session.',
    ],
];
