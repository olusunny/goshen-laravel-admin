<?php

use Illuminate\Support\Facades\Route;
use Sunny\Fundraising\Http\Controllers\Admin\CampaignController;

Route::get('/', [CampaignController::class, 'index'])->name('index');
