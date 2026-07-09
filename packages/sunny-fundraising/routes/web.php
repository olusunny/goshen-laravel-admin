<?php

use Illuminate\Support\Facades\Route;
use Sunny\Fundraising\Http\Controllers\Web\CampaignController;

Route::get('/', [CampaignController::class, 'index'])->name('index');
Route::get('{campaign}', [CampaignController::class, 'show'])->name('show');
