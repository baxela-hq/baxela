<?php

use Illuminate\Support\Facades\Route;
use Modules\Contact\Http\Controllers\Public\ContactMessage\SubmitContactMessageController;

Route::prefix('public')->name('public.')->group(function () {
    Route::post('/messages', SubmitContactMessageController::class)
        ->name('messages.submit')
        ->middleware('throttle:contact-submit');
});
