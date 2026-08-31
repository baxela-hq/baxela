<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentController;
use Modules\Payment\Http\Controllers\Admin\Payment\UpdatePaymentController;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payments', ListPaymentController::class)->name('payments.list');
    Route::patch('/payments/{id}', UpdatePaymentController::class)->name('payments.update');
});
