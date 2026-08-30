<?php

use Illuminate\Support\Facades\Route;
use Modules\Shipping\Http\Controllers\User\Method\ListMethodController;
use Modules\Shipping\Http\Controllers\User\Shipment\ShowShipmentController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/methods', ListMethodController::class)->name('methods.list');
    Route::get('/shipments/{orderId}', ShowShipmentController::class)->name('shipments.show');
});
