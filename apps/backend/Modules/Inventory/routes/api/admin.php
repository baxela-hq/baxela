<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\CreateInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\DeleteInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\ListInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\ShowInventoryStockController;
use Modules\Inventory\Http\Controllers\Admin\InventoryStock\UpdateInventoryStockController;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/inventory-stocks', ListInventoryStockController::class)->name('inventory-stocks.list');
    Route::post('/inventory-stocks', CreateInventoryStockController::class)->name('inventory-stocks.create');
    Route::get('/inventory-stocks/{id}', ShowInventoryStockController::class)->name('inventory-stocks.show');
    Route::patch('/inventory-stocks/{id}', UpdateInventoryStockController::class)->name('inventory-stocks.update');
    Route::delete('/inventory-stocks/{id}', DeleteInventoryStockController::class)->name('inventory-stocks.delete');
});
