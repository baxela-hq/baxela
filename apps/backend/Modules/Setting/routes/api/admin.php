<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Setting\Http\Controllers\Admin\Data\ExportSettingDataController;
use Modules\Setting\Http\Controllers\Admin\Data\ImportSettingDataController;
use Modules\Setting\Http\Controllers\Admin\Data\ListSettingDataImportsController;
use Modules\Setting\Http\Controllers\Admin\Data\PreviewSettingDataImportController;
use Modules\Setting\Http\Controllers\Admin\Setting\ListSettingController;
use Modules\Setting\Http\Controllers\Admin\Setting\UpdateSettingController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings', ListSettingController::class)->name('settings.list');
    Route::patch('/settings', UpdateSettingController::class)->name('settings.update');
    // The module's own data-transfer endpoints (per-module surface from
    // the transfer standard's phase plan; the central transfer endpoints
    // remain available).
    Route::get('/data/export', ExportSettingDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewSettingDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportSettingDataController::class)->name('data.import');
    Route::get('/data/imports', ListSettingDataImportsController::class)->name('data.imports.list');
});
