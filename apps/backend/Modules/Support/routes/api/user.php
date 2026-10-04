<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\User\Ticket\CreateTicketController;
use Modules\Support\Http\Controllers\User\Ticket\ListTicketController;
use Modules\Support\Http\Controllers\User\Ticket\ShowTicketController;
use Modules\Support\Http\Controllers\User\Ticket\UpdateTicketStatusController;
use Modules\Support\Http\Controllers\User\TicketMessage\CreateTicketMessageController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/tickets', ListTicketController::class)->name('tickets.list');
    Route::post('/tickets', CreateTicketController::class)
        ->name('tickets.create')
        ->middleware('throttle:support-ticket-create');
    Route::get('/tickets/{id}', ShowTicketController::class)->name('tickets.show');
    Route::patch('/tickets/{id}/status', UpdateTicketStatusController::class)->name('tickets.update-status');
    Route::post('/tickets/{id}/messages', CreateTicketMessageController::class)
        ->name('ticket-messages.create')
        ->middleware('throttle:support-ticket-reply');
});
