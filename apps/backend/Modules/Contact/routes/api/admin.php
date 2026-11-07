<?php

use Illuminate\Support\Facades\Route;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\DeleteContactMessageController;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\ListContactMessageController;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\ShowContactMessageController;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\UpdateContactMessageStatusController;
use Modules\Contact\Http\Controllers\Admin\Data\ExportContactDataController;
use Modules\Contact\Http\Controllers\Admin\Data\ImportContactDataController;
use Modules\Contact\Http\Controllers\Admin\Data\ListContactDataImportsController;
use Modules\Contact\Http\Controllers\Admin\Data\PreviewContactDataImportController;
use Modules\Contact\Http\Controllers\Admin\NewsletterSubscriber\DeleteNewsletterSubscriberController;
use Modules\Contact\Http\Controllers\Admin\NewsletterSubscriber\ListNewsletterSubscriberController;
use Modules\Contact\Http\Controllers\Admin\NewsletterSubscriber\UpdateNewsletterSubscriberStatusController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/messages', ListContactMessageController::class)->name('messages.list');
    Route::get('/messages/{id}', ShowContactMessageController::class)->name('messages.show');
    Route::patch('/messages/{id}/status', UpdateContactMessageStatusController::class)->name('messages.update-status');
    Route::delete('/messages/{id}', DeleteContactMessageController::class)->name('messages.delete');

    Route::get('/newsletter-subscribers', ListNewsletterSubscriberController::class)->name('newsletter-subscribers.list');
    Route::patch('/newsletter-subscribers/{id}/status', UpdateNewsletterSubscriberStatusController::class)->name('newsletter-subscribers.update-status');
    Route::delete('/newsletter-subscribers/{id}', DeleteNewsletterSubscriberController::class)->name('newsletter-subscribers.delete');
    // The module's own data-transfer endpoints (per-module surface from
    // the transfer standard's phase plan; the central transfer endpoints
    // remain available).
    Route::get('/data/export', ExportContactDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewContactDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportContactDataController::class)->name('data.import');
    Route::get('/data/imports', ListContactDataImportsController::class)->name('data.imports.list');
});
