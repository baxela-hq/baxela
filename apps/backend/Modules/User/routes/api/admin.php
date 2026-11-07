<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\User\Http\Controllers\Admin\Data\ExportUserDataController;
use Modules\User\Http\Controllers\Admin\Data\ImportUserDataController;
use Modules\User\Http\Controllers\Admin\Data\ListUserDataImportsController;
use Modules\User\Http\Controllers\Admin\Data\PreviewUserDataImportController;

// The module's own data-transfer endpoints (export-only surface; the
// central transfer endpoints remain available).
Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/data/export', ExportUserDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewUserDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportUserDataController::class)->name('data.import');
    Route::get('/data/imports', ListUserDataImportsController::class)->name('data.imports.list');
});
