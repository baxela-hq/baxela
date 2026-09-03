<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\Public\Payment\HandleWebhookController;

Route::prefix('public')->name('public.')->group(function () {
    Route::post('/webhook/{driver}', HandleWebhookController::class)->name('webhook');
});
