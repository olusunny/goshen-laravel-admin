<?php

use ChurchTools\GoshenPrayerAttendance\Filament\Http\PrayerSessionQrController;
use Illuminate\Support\Facades\Route;

Route::get('sessions/{session}/qr', PrayerSessionQrController::class)
    ->whereNumber('session')
    ->name('sessions.qr');
