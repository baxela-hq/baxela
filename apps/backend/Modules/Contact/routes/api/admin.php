<?php

use Illuminate\Support\Facades\Route;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\DeleteContactMessageController;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\ListContactMessageController;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\ShowContactMessageController;
use Modules\Contact\Http\Controllers\Admin\ContactMessage\UpdateContactMessageStatusController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/messages', ListContactMessageController::class)->name('messages.list');
    Route::get('/messages/{id}', ShowContactMessageController::class)->name('messages.show');
    Route::patch('/messages/{id}/status', UpdateContactMessageStatusController::class)->name('messages.update-status');
    Route::delete('/messages/{id}', DeleteContactMessageController::class)->name('messages.delete');
});
