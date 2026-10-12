<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\User\Notification\GetUnreadNotificationCountController;
use Modules\Notification\Http\Controllers\User\Notification\ListNotificationController;
use Modules\Notification\Http\Controllers\User\Notification\MarkAllNotificationsReadController;
use Modules\Notification\Http\Controllers\User\Notification\MarkNotificationReadController;
use Modules\Notification\Http\Controllers\User\PushSubscription\DeletePushSubscriptionController;
use Modules\Notification\Http\Controllers\User\PushSubscription\ListPushSubscriptionsController;
use Modules\Notification\Http\Controllers\User\PushSubscription\UpsertPushSubscriptionController;
use Modules\Notification\Http\Controllers\User\Telegram\DeleteTelegramSubscriptionController;
use Modules\Notification\Http\Controllers\User\Telegram\GetTelegramSubscriptionController;
use Modules\Notification\Http\Controllers\User\Telegram\LinkTelegramController;
use Modules\Notification\Http\Controllers\User\Telegram\SendTelegramTestController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/notifications', ListNotificationController::class)->name('notifications.list');
    Route::get('/notifications/unread-count', GetUnreadNotificationCountController::class)->name('notifications.unread-count');
    Route::patch('/notifications/{id}/read', MarkNotificationReadController::class)->whereNumber('id')->name('notifications.read');
    Route::patch('/notifications/read-all', MarkAllNotificationsReadController::class)->name('notifications.read-all');

    // Browser push registrations; keyed by endpoint (delete takes it in
    // the body — a URL cannot ride safely as a path parameter).
    Route::get('/push-subscriptions', ListPushSubscriptionsController::class)->name('push-subscriptions.list');
    Route::post('/push-subscriptions', UpsertPushSubscriptionController::class)->name('push-subscriptions.upsert');
    Route::delete('/push-subscriptions', DeletePushSubscriptionController::class)->name('push-subscriptions.delete');

    // Telegram linking; the code comes from the bot's /start reply.
    Route::get('/telegram/subscription', GetTelegramSubscriptionController::class)->name('telegram.subscription');
    Route::post('/telegram/link', LinkTelegramController::class)->name('telegram.link');
    Route::delete('/telegram/subscription', DeleteTelegramSubscriptionController::class)->name('telegram.unlink');
    Route::post('/telegram/test', SendTelegramTestController::class)->name('telegram.test');
});
