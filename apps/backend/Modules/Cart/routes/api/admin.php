<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\Admin\Data\ExportCartDataController;
use Modules\Cart\Http\Controllers\Admin\Data\ImportCartDataController;
use Modules\Cart\Http\Controllers\Admin\Data\ListCartDataImportsController;
use Modules\Cart\Http\Controllers\Admin\Data\PreviewCartDataImportController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

// The module's own data-transfer endpoints (export-only surface; the
// central transfer endpoints remain available).
Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/data/export', ExportCartDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewCartDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportCartDataController::class)->name('data.import');
    Route::get('/data/imports', ListCartDataImportsController::class)->name('data.imports.list');
});
