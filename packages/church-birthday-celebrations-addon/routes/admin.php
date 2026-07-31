<?php

use ChurchTools\ChurchBirthdayCelebrations\Http\Controllers\Api\BirthdayCelebrationController;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => response()->json(['status' => 'ok', 'capability' => config('church-birthday-celebrations.capability')]))->name('health');
