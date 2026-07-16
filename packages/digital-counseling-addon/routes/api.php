<?php

use ChurchTools\DigitalCounseling\Http\Controllers\Api\CounselingCaseController;
use ChurchTools\DigitalCounseling\Http\Controllers\Api\CounselingMessageController;
use Illuminate\Support\Facades\Route;

Route::get('cases', [CounselingCaseController::class, 'index'])->name('cases.index');
Route::post('cases', [CounselingCaseController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('cases.store');
Route::get('cases/{counselingCase}', [CounselingCaseController::class, 'show'])
    ->whereNumber('counselingCase')
    ->name('cases.show');
Route::post('cases/{counselingCase}/close', [CounselingCaseController::class, 'close'])
    ->whereNumber('counselingCase')
    ->middleware('throttle:10,1')
    ->name('cases.close');
Route::post('cases/{counselingCase}/messages', [CounselingMessageController::class, 'store'])
    ->whereNumber('counselingCase')
    ->middleware('throttle:12,1')
    ->name('cases.messages.store');
Route::post('cases/{counselingCase}/messages/{message}/reaction', [CounselingMessageController::class, 'reaction'])
    ->whereNumber('counselingCase')
    ->whereNumber('message')
    ->middleware('throttle:30,1')
    ->name('cases.messages.reaction');
Route::get('cases/{counselingCase}/messages/{message}/audio', [CounselingMessageController::class, 'audio'])
    ->whereNumber('counselingCase')
    ->whereNumber('message')
    ->name('cases.messages.audio');
Route::get('cases/{counselingCase}/messages/{message}/media', [CounselingMessageController::class, 'media'])
    ->whereNumber('counselingCase')
    ->whereNumber('message')
    ->name('cases.messages.media');
