<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Support\Http\Controllers\Admin\Ticket\DeleteTicketController;
use Modules\Support\Http\Controllers\Admin\Ticket\ListTicketController;
use Modules\Support\Http\Controllers\Admin\Ticket\ShowTicketController;
use Modules\Support\Http\Controllers\Admin\Ticket\UpdateTicketStatusController;
use Modules\Support\Http\Controllers\Admin\TicketMessage\CreateTicketMessageController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tickets', ListTicketController::class)->name('tickets.list');
    Route::get('/tickets/{id}', ShowTicketController::class)->name('tickets.show');
    Route::patch('/tickets/{id}/status', UpdateTicketStatusController::class)->name('tickets.update-status');
    Route::delete('/tickets/{id}', DeleteTicketController::class)->name('tickets.delete');

    Route::post('/tickets/{id}/messages', CreateTicketMessageController::class)->name('ticket-messages.create');
});
