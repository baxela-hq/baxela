<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Inventory\Http\Controllers\Admin\Data\ExportInventoryDataController;
use Modules\Inventory\Http\Controllers\Admin\Data\ImportInventoryDataController;
use Modules\Inventory\Http\Controllers\Admin\Data\ListInventoryDataImportsController;
use Modules\Inventory\Http\Controllers\Admin\Data\PreviewInventoryDataImportController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\CreateInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\DeleteInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\ListInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\ShowInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\UpdateInventoryStockController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/inventory-stocks', ListInventoryStockController::class)->name('inventory-stocks.list');
    Route::post('/inventory-stocks', CreateInventoryStockController::class)->name('inventory-stocks.create');
    Route::get('/inventory-stocks/{id}', ShowInventoryStockController::class)->name('inventory-stocks.show');
    Route::patch('/inventory-stocks/{id}', UpdateInventoryStockController::class)->name('inventory-stocks.update');
    Route::delete('/inventory-stocks/{id}', DeleteInventoryStockController::class)->name('inventory-stocks.delete');
    // The module's own data-transfer endpoints (per-module surface from
    // the transfer standard's phase plan; the central transfer endpoints
    // remain available).
    Route::get('/data/export', ExportInventoryDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewInventoryDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportInventoryDataController::class)->name('data.import');
    Route::get('/data/imports', ListInventoryDataImportsController::class)->name('data.imports.list');
});
