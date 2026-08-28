<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\User\Payment\CreatePaymentController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::post('/process', CreatePaymentController::class)->name('payments.process');
});
