<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Order\Http\Controllers\Admin\Order\ListOrderController;
use Modules\Order\Http\Controllers\Admin\Order\ShowOrderController;
use Modules\Order\Http\Controllers\Admin\Order\UpdateOrderController;
use Modules\Order\Http\Controllers\Admin\OrderItem\ListOrderItemController;
use Modules\Order\Http\Controllers\Admin\OrderReturn\ListOrderReturnController;
use Modules\Order\Http\Controllers\Admin\OrderReturn\ShowOrderReturnController;
use Modules\Order\Http\Controllers\Admin\OrderReturn\UpdateOrderReturnController;
use Modules\Order\Http\Controllers\Admin\Stats\ShowStatsController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/stats', ShowStatsController::class)->name('stats.show');
    Route::get('/orders', ListOrderController::class)->name('orders.list');
    Route::get('/orders/{id}', ShowOrderController::class)->name('orders.show');
    Route::patch('/orders/{id}', UpdateOrderController::class)->name('orders.update');
    Route::get('/orders/{id}/items', ListOrderItemController::class)->name('orderItems.list');

    Route::get('/returns', ListOrderReturnController::class)->name('returns.list');
    Route::get('/returns/{id}', ShowOrderReturnController::class)->name('returns.show');
    Route::patch('/returns/{id}', UpdateOrderReturnController::class)->name('returns.update');
});
