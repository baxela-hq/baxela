<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Notification\Http\Controllers\Admin\Data\ExportNotificationDataController;
use Modules\Notification\Http\Controllers\Admin\Data\ImportNotificationDataController;
use Modules\Notification\Http\Controllers\Admin\Data\ListNotificationDataImportsController;
use Modules\Notification\Http\Controllers\Admin\Data\PreviewNotificationDataImportController;
use Modules\Notification\Http\Controllers\Admin\Notification\GetUnreadNotificationCountController;
use Modules\Notification\Http\Controllers\Admin\Notification\ListNotificationController;
use Modules\Notification\Http\Controllers\Admin\Notification\MarkAllNotificationsReadController;
use Modules\Notification\Http\Controllers\Admin\Notification\MarkNotificationReadController;
use Modules\Notification\Http\Controllers\Admin\NotificationPreference\ListNotificationPreferenceController;
use Modules\Notification\Http\Controllers\Admin\NotificationPreference\UpdateNotificationPreferenceController;
use Modules\Notification\Http\Controllers\Admin\PushSubscription\DeletePushSubscriptionController;
use Modules\Notification\Http\Controllers\Admin\PushSubscription\ListPushSubscriptionsController;
use Modules\Notification\Http\Controllers\Admin\PushSubscription\UpsertPushSubscriptionController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/notifications', ListNotificationController::class)->name('notifications.list');
    Route::get('/notifications/unread-count', GetUnreadNotificationCountController::class)->name('notifications.unread-count');
    Route::patch('/notifications/{id}/read', MarkNotificationReadController::class)->whereNumber('id')->name('notifications.read');
    Route::patch('/notifications/read-all', MarkAllNotificationsReadController::class)->name('notifications.read-all');

    // Per-account channel toggles for admin-configurable notifications.
    Route::get('/notification-preferences', ListNotificationPreferenceController::class)->name('notification-preferences.list');
    Route::patch('/notification-preferences', UpdateNotificationPreferenceController::class)->name('notification-preferences.update');

    // Browser push registrations; keyed by endpoint (delete takes it in
    // the body — a URL cannot ride safely as a path parameter).
    Route::get('/push-subscriptions', ListPushSubscriptionsController::class)->name('push-subscriptions.list');
    Route::post('/push-subscriptions', UpsertPushSubscriptionController::class)->name('push-subscriptions.upsert');
    Route::delete('/push-subscriptions', DeletePushSubscriptionController::class)->name('push-subscriptions.delete');
    // The module's own data-transfer endpoints (per-module surface from
    // the transfer standard's phase plan; the central transfer endpoints
    // remain available).
    Route::get('/data/export', ExportNotificationDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewNotificationDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportNotificationDataController::class)->name('data.import');
    Route::get('/data/imports', ListNotificationDataImportsController::class)->name('data.imports.list');
});
