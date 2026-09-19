<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Notification\Http\Controllers\Admin\Notification\GetUnreadNotificationCountController;
use Modules\Notification\Http\Controllers\Admin\Notification\ListNotificationController;
use Modules\Notification\Http\Controllers\Admin\Notification\MarkAllNotificationsReadController;
use Modules\Notification\Http\Controllers\Admin\Notification\MarkNotificationReadController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/notifications', ListNotificationController::class)->name('notifications.list');
    Route::get('/notifications/unread-count', GetUnreadNotificationCountController::class)->name('notifications.unread-count');
    Route::patch('/notifications/{id}/read', MarkNotificationReadController::class)->name('notifications.read');
    Route::patch('/notifications/read-all', MarkAllNotificationsReadController::class)->name('notifications.read-all');
});
