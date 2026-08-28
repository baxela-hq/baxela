<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentController;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payments', ListPaymentController::class)->name('payments.list');
});
