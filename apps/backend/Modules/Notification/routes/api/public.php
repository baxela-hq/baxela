<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\Public\Telegram\BotInfoController;
use Modules\Notification\Http\Controllers\Public\WebPush\VapidPublicKeyController;

Route::prefix('webpush')->name('webpush.')->group(function () {
    Route::get('/vapid-public-key', VapidPublicKeyController::class)->name('vapid-public-key');
});

Route::prefix('telegram')->name('telegram.')->group(function () {
    Route::get('/bot-info', BotInfoController::class)->name('bot-info');
});
