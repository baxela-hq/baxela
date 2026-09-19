<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\User\Notification\GetUnreadNotificationCountController;
use Modules\Notification\Http\Controllers\User\Notification\ListNotificationController;
use Modules\Notification\Http\Controllers\User\Notification\MarkAllNotificationsReadController;
use Modules\Notification\Http\Controllers\User\Notification\MarkNotificationReadController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/notifications', ListNotificationController::class)->name('notifications.list');
    Route::get('/notifications/unread-count', GetUnreadNotificationCountController::class)->name('notifications.unread-count');
    Route::patch('/notifications/{id}/read', MarkNotificationReadController::class)->whereNumber('id')->name('notifications.read');
    Route::patch('/notifications/read-all', MarkAllNotificationsReadController::class)->name('notifications.read-all');
});
