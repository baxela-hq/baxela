<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Order\Http\Controllers\Admin\Order\ListOrderController;
use Modules\Order\Http\Controllers\Admin\Order\ShowOrderController;
use Modules\Order\Http\Controllers\Admin\Order\UpdateOrderController;
use Modules\Order\Http\Controllers\Admin\OrderItem\ListOrderItemController;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders', ListOrderController::class)->name('orders.list');
    Route::get('/orders/{id}', ShowOrderController::class)->name('orders.show');
    Route::patch('/orders/{id}', UpdateOrderController::class)->name('orders.update');
    Route::get('/orders/{id}/items', ListOrderItemController::class)->name('orderItems.list');
});
