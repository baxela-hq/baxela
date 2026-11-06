<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Transfer\Http\Controllers\Admin\Capabilities\ShowTransferCapabilitiesController;
use Modules\Transfer\Http\Controllers\Admin\Export\ExportTransferController;
use Modules\Transfer\Http\Controllers\Admin\Import\ImportTransferController;
use Modules\Transfer\Http\Controllers\Admin\Import\ListTransferImportController;
use Modules\Transfer\Http\Controllers\Admin\Import\PreviewTransferImportController;
use Modules\Transfer\Http\Controllers\Admin\Import\ShowTransferImportController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/capabilities', ShowTransferCapabilitiesController::class)->name('capabilities.show');
    Route::get('/exports/{module}', ExportTransferController::class)->name('exports.show');
    Route::post('/imports/preview', PreviewTransferImportController::class)->name('imports.preview');
    Route::post('/imports', ImportTransferController::class)->name('imports.create');
    Route::get('/imports', ListTransferImportController::class)->name('imports.list');
    Route::get('/imports/{id}', ShowTransferImportController::class)->name('imports.show');
});
