<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\User\Order\CancelOrderController;
use Modules\Order\Http\Controllers\User\Order\ListOrderController;
use Modules\Order\Http\Controllers\User\Order\ShowOrderController;
use Modules\Order\Http\Controllers\User\OrderItem\ListOrderItemController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/orders', ListOrderController::class)->name('orders.list');
    Route::get('/orders/{code}', ShowOrderController::class)->name('orders.show');
    Route::patch('/orders/{code}/cancel', CancelOrderController::class)->name('orders.cancel');
    Route::get('/orders/{code}/items', ListOrderItemController::class)->name('order-items.list');
});
