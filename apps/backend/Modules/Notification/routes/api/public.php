<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\Public\WebPush\VapidPublicKeyController;

Route::prefix('webpush')->name('webpush.')->group(function () {
    Route::get('/vapid-public-key', VapidPublicKeyController::class)->name('vapid-public-key');
});
