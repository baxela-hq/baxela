<?php

use Illuminate\Support\Facades\Route;
use Modules\Contact\Http\Controllers\Public\ContactMessage\SubmitContactMessageController;
use Modules\Contact\Http\Controllers\Public\NewsletterSubscriber\SubscribeNewsletterController;

Route::prefix('public')->name('public.')->group(function () {
    Route::post('/messages', SubmitContactMessageController::class)
        ->name('messages.submit')
        ->middleware('throttle:contact-submit');

    Route::post('/newsletter-subscribers', SubscribeNewsletterController::class)
        ->name('newsletter-subscribers.subscribe')
        ->middleware('throttle:contact-subscribe');
});
