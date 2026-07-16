<?php

use ChurchTools\DigitalCounseling\Http\Controllers\Admin\CounselingMessageMediaController;
use Illuminate\Support\Facades\Route;

Route::get('counseling/messages/{message}/media', CounselingMessageMediaController::class)
    ->whereNumber('message')
    ->name('messages.media');
