<?php

use ChurchTools\ChurchBirthdayCelebrations\Http\Controllers\Api\BirthdayCelebrationController;
use ChurchTools\ChurchBirthdayCelebrations\Http\Middleware\AuthenticateBirthdayRequester;
use Illuminate\Support\Facades\Route;

Route::get('context', [BirthdayCelebrationController::class, 'context'])->name('context');
Route::put('preferences', [BirthdayCelebrationController::class, 'updatePreferences'])->middleware('throttle:10,1')->name('preferences.update');
Route::get('hub', [BirthdayCelebrationController::class, 'hub'])
    ->withoutMiddleware(AuthenticateBirthdayRequester::class)
    ->middleware('throttle:30,1')
    ->name('hub');
Route::get('celebrations/{publicId}', [BirthdayCelebrationController::class, 'show'])->name('celebrations.show');
Route::get('celebrations/{publicId}/card', [BirthdayCelebrationController::class, 'card'])->middleware('throttle:10,1')->name('celebrations.card');
Route::put('celebrations/{publicId}/reaction', [BirthdayCelebrationController::class, 'react'])->middleware('throttle:20,1')->name('celebrations.reaction');
Route::put('celebrations/{publicId}/greeting', [BirthdayCelebrationController::class, 'greet'])->middleware('throttle:8,1')->name('celebrations.greeting');
Route::delete('celebrations/{publicId}/greetings/{greetingId}', [BirthdayCelebrationController::class, 'deleteGreeting'])->middleware('throttle:8,1')->name('celebrations.greeting.delete');
Route::put('celebrations/{publicId}/thank-you', [BirthdayCelebrationController::class, 'thank'])->middleware('throttle:5,1')->name('celebrations.thank');
Route::post('celebrations/{publicId}/greetings/{greetingId}/report', [BirthdayCelebrationController::class, 'report'])->middleware('throttle:5,1')->name('celebrations.report');
