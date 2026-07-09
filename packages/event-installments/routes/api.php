<?php

use Illuminate\Support\Facades\Route;
use Personal\EventInstallments\Http\Controllers\Api\BookingController;
use Personal\EventInstallments\Http\Controllers\Api\EventController;
use Personal\EventInstallments\Http\Controllers\Api\TicketCheckInController;
use Personal\EventInstallments\Http\Controllers\Api\TicketController;

Route::middleware(config('event-installments.middleware.auth'))->group(function () {
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);
    Route::get('events/{event}/tickets', [TicketController::class, 'index']);
    Route::get('events/{event}/tickets/updated', [TicketController::class, 'updated']);
    Route::get('tickets/{identifier}', [TicketController::class, 'show']);
    Route::post('tickets/{identifier}/check-ins', [TicketCheckInController::class, 'store']);
    Route::post('tickets/bulk-check-ins', [TicketCheckInController::class, 'bulkStore']);
    Route::post('tickets/{identifier}/days/{day}/check-ins', [TicketCheckInController::class, 'storeForDay']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::get('bookings/{booking}', [BookingController::class, 'show']);
    Route::post('bookings/{booking}/installments/{installment}/checkout', [BookingController::class, 'checkout']);
});
