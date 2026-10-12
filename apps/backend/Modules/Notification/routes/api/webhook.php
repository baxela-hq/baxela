<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\Webhook\Telegram\HandleTelegramWebhookController;

Route::middleware('throttle:notification-webhook')->prefix('webhook')->name('webhook.')->group(function () {
    Route::post('/telegram', HandleTelegramWebhookController::class)->name('telegram');
});
