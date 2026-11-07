<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Transfer\Http\Controllers\Admin\Capabilities\ShowTransferCapabilitiesController;
use Modules\Transfer\Http\Controllers\Admin\Import\ListTransferImportController;
use Modules\Transfer\Http\Controllers\Admin\Import\ShowTransferImportController;

// The central registry + audit plane of the data-transfer standard.
// File export/import live in each module's own admin/data/* endpoints;
// the central file endpoints were removed in the phase-4 migration.

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/capabilities', ShowTransferCapabilitiesController::class)->name('capabilities.show');
    Route::get('/imports', ListTransferImportController::class)->name('imports.list');
    Route::get('/imports/{id}', ShowTransferImportController::class)->name('imports.show');
});
