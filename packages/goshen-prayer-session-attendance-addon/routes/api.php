<?php

use ChurchTools\GoshenPrayerAttendance\Http\Controllers\Api\PrayerAttendanceController;
use ChurchTools\GoshenPrayerAttendance\Http\Controllers\Api\PrayerSessionControlController;
use Illuminate\Support\Facades\Route;

Route::get('sessions/active', [PrayerAttendanceController::class, 'active'])->name('sessions.active');
Route::post('confirmations/self', [PrayerAttendanceController::class, 'selfConfirm'])->middleware('throttle:8,1')->name('confirmations.self');
Route::get('sessions/{session}/staff/tickets/{identifier}', [PrayerAttendanceController::class, 'staffLookup'])->middleware('throttle:30,1')->name('staff.tickets.lookup');
Route::post('sessions/{session}/staff/confirmations', [PrayerAttendanceController::class, 'staffConfirm'])->middleware('throttle:20,1')->name('staff.confirmations.store');
Route::post('staff/sync', [PrayerAttendanceController::class, 'staffSync'])->middleware('throttle:20,1')->name('staff.sync');
Route::get('sessions/{session}/qr', [PrayerAttendanceController::class, 'mobileQr'])->middleware('throttle:10,1')->name('sessions.qr');

// Flutter's first dormant build used these concise paths. Keep them as
// documented compatibility aliases so capability-gated clients can upgrade
// independently of the package ZIP.
Route::get('context', [PrayerAttendanceController::class, 'context'])->name('context');
Route::post('confirm', [PrayerAttendanceController::class, 'selfConfirm'])->middleware('throttle:8,1')->name('confirm.compat');
Route::post('sessions/{session}/staff-confirm', [PrayerAttendanceController::class, 'staffConfirm'])->middleware('throttle:20,1')->name('staff.confirm.compat');

Route::get('control/sessions', [PrayerSessionControlController::class, 'index'])->name('control.sessions.index');
Route::post('control/sessions', [PrayerSessionControlController::class, 'store'])->middleware('throttle:8,1')->name('control.sessions.store');
Route::post('control/sessions/{session}/activate', [PrayerSessionControlController::class, 'activate'])->middleware('throttle:4,1')->name('control.sessions.activate');
Route::post('control/sessions/{session}/close', [PrayerSessionControlController::class, 'close'])->middleware('throttle:4,1')->name('control.sessions.close');
Route::post('control/sessions/{session}/reopen', [PrayerSessionControlController::class, 'reopen'])->middleware('throttle:3,1')->name('control.sessions.reopen');
Route::post('control/sessions/{session}/reminder', [PrayerSessionControlController::class, 'reminder'])->middleware('throttle:2,1')->name('control.sessions.reminder');
Route::get('control/sessions/{session}/report', [PrayerSessionControlController::class, 'report'])->name('control.sessions.report');
Route::get('control/sessions/{session}/export.csv', [PrayerSessionControlController::class, 'export'])->middleware('throttle:5,1')->name('control.sessions.export');

Route::get('sessions', [PrayerSessionControlController::class, 'index'])->name('sessions.index.compat');
Route::post('sessions/{session}/activate', [PrayerSessionControlController::class, 'activate'])->middleware('throttle:4,1')->name('sessions.activate.compat');
Route::post('sessions/{session}/close', [PrayerSessionControlController::class, 'close'])->middleware('throttle:4,1')->name('sessions.close.compat');
Route::post('sessions/{session}/remind', [PrayerSessionControlController::class, 'reminder'])->middleware('throttle:2,1')->name('sessions.remind.compat');
