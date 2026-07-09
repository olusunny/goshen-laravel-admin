<?php

use Illuminate\Support\Facades\Route;
use Sunny\Fundraising\Http\Controllers\Api\CampaignController;

Route::controller(CampaignController::class)->group(function (): void {
    Route::get('campaigns/active', 'active')->name('campaigns.active');
    Route::post('management/summary', 'managementSummary')->name('management.summary');
    Route::post('management/campaigns/{campaign}/status', 'updateManagementStatus')->name('management.campaigns.status');
    Route::get('campaigns/{campaign}', 'show')->name('campaigns.show');
    Route::post('campaigns/{campaign}/contribute', 'contribute')
        ->middleware('throttle:6,1')
        ->name('campaigns.contribute');
    Route::post('campaigns/{campaign}/checkout', 'checkout')
        ->middleware('throttle:6,1')
        ->name('campaigns.checkout');
    Route::post('stripe/webhook', 'stripeWebhook')
        ->name('stripe.webhook');
});
