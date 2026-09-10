<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\Webhook\Payment\HandleWebhookController;

Route::middleware('throttle:webhook')->prefix('webhook')->name('webhook.')->group(function () {
    Route::post('/{driver}', HandleWebhookController::class)->name('handle');
});
