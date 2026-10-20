<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\User\Order\CancelOrderController;
use Modules\Order\Http\Controllers\User\Order\ListOrderController;
use Modules\Order\Http\Controllers\User\Order\ShowOrderController;
use Modules\Order\Http\Controllers\User\OrderItem\ListOrderItemController;
use Modules\Order\Http\Controllers\User\OrderReturn\CancelOrderReturnController;
use Modules\Order\Http\Controllers\User\OrderReturn\CreateOrderReturnController;
use Modules\Order\Http\Controllers\User\OrderReturn\ListOrderReturnController;
use Modules\Order\Http\Controllers\User\OrderReturn\MarkOrderReturnShippedController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/orders', ListOrderController::class)->name('orders.list');
    Route::get('/orders/{code}', ShowOrderController::class)->name('orders.show');
    Route::patch('/orders/{code}/cancel', CancelOrderController::class)->name('orders.cancel');
    Route::get('/orders/{code}/items', ListOrderItemController::class)->name('order-items.list');

    Route::get('/returns', ListOrderReturnController::class)->name('returns.list');
    Route::post('/orders/{code}/returns', CreateOrderReturnController::class)->name('returns.create');
    Route::patch('/returns/{id}/shipped', MarkOrderReturnShippedController::class)->name('returns.shipped');
    Route::patch('/returns/{id}/cancel', CancelOrderReturnController::class)->name('returns.cancel');
});
