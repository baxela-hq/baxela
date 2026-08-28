<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\User\Order\ListOrderController;
use Modules\Order\Http\Controllers\User\Order\ShowOrderController;
use Modules\Order\Http\Controllers\User\OrderItem\ListOrderItemController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/orders', ListOrderController::class)->name('orders.list');
    Route::get('/orders/{id}', ShowOrderController::class)->name('orders.show');
    Route::get('/orders/{id}/items', ListOrderItemController::class)->name('order-items.list');
});
