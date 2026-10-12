<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\Admin\AuditLog\ListAuditLogsController;
use Modules\Audit\Http\Controllers\Admin\AuditLog\ShowAuditLogController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/logs', ListAuditLogsController::class)->name('logs.list');
    Route::get('/logs/{id}', ShowAuditLogController::class)->name('logs.show');
});
