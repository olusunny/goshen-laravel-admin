<?php

use Illuminate\Support\Facades\Route;
use Personal\EventInstallments\Http\Controllers\Admin\EventController;
use Personal\EventInstallments\Http\Controllers\Admin\EventScheduleController;
use Personal\EventInstallments\Http\Controllers\Admin\PaymentPlanController;
use Personal\EventInstallments\Http\Controllers\Admin\TicketDocumentController;
use Personal\EventInstallments\Http\Controllers\Admin\TicketEmailController;
use Personal\EventInstallments\Http\Controllers\Admin\TicketTypeController;

Route::get('/', fn () => redirect()->route('event-installments.events.index'))->name('dashboard');

Route::resource('events', EventController::class);
Route::post('events/{event}/schedules', [EventScheduleController::class, 'store'])->name('events.schedules.store');
Route::delete('events/{event}/schedules/{schedule}', [EventScheduleController::class, 'destroy'])->name('events.schedules.destroy');
Route::post('events/{event}/ticket-types', [TicketTypeController::class, 'store'])->name('events.ticket-types.store');
Route::delete('events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])->name('events.ticket-types.destroy');
Route::post('events/{event}/payment-plans', [PaymentPlanController::class, 'store'])->name('events.payment-plans.store');
Route::delete('events/{event}/payment-plans/{paymentPlan}', [PaymentPlanController::class, 'destroy'])->name('events.payment-plans.destroy');

Route::get('tickets/{ticket}/documents/{type}', TicketDocumentController::class)
    ->middleware('signed')
    ->name('tickets.documents.show');

Route::post('tickets/{ticket}/email', TicketEmailController::class)->name('tickets.email');
