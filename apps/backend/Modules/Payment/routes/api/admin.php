<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentController;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentMethodController;
use Modules\Payment\Http\Controllers\Admin\Payment\UpdatePaymentController;
use Modules\Payment\Http\Controllers\Admin\Payment\UpdatePaymentMethodController;
use Modules\Payment\Http\Controllers\Admin\PaymentRefund\CreatePaymentRefundController;
use Modules\Payment\Http\Controllers\Admin\PaymentRefund\ListPaymentRefundController;
use Modules\Payment\Http\Controllers\Admin\PaymentRefund\ResolvePaymentRefundController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payments', ListPaymentController::class)->name('payments.list');
    Route::patch('/payments/{id}', UpdatePaymentController::class)->name('payments.update');

    Route::get('/payments/{id}/refunds', ListPaymentRefundController::class)->name('payments.refunds.list');
    Route::post('/payments/{id}/refunds', CreatePaymentRefundController::class)->name('payments.refunds.create');
    Route::patch('/refunds/{id}/resolve', ResolvePaymentRefundController::class)->name('refunds.resolve');

    Route::get('/methods', ListPaymentMethodController::class)->name('methods.list');
    Route::patch('/methods/{id}', UpdatePaymentMethodController::class)->name('methods.update');
});
