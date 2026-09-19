<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\User\Payment\CreatePaymentController;
use Modules\Payment\Http\Controllers\User\Payment\ListPaymentMethodsController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/methods', ListPaymentMethodsController::class)->name('payments.methods');
    Route::post('/process', CreatePaymentController::class)->name('payments.process');
});
