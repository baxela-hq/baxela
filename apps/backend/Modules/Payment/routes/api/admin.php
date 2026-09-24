<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentController;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentMethodController;
use Modules\Payment\Http\Controllers\Admin\Payment\UpdatePaymentController;
use Modules\Payment\Http\Controllers\Admin\Payment\UpdatePaymentMethodController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payments', ListPaymentController::class)->name('payments.list');
    Route::patch('/payments/{id}', UpdatePaymentController::class)->name('payments.update');

    Route::get('/methods', ListPaymentMethodController::class)->name('methods.list');
    Route::patch('/methods/{id}', UpdatePaymentMethodController::class)->name('methods.update');
});
