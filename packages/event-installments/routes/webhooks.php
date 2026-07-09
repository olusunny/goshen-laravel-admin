<?php

use Illuminate\Support\Facades\Route;
use Personal\EventInstallments\Http\Controllers\Api\PaymentWebhookController;

Route::post('{gateway}', PaymentWebhookController::class);
