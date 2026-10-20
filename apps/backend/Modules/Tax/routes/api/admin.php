<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Tax\Http\Controllers\Admin\TaxClass\CreateTaxClassController;
use Modules\Tax\Http\Controllers\Admin\TaxClass\DeleteTaxClassController;
use Modules\Tax\Http\Controllers\Admin\TaxClass\ListTaxClassController;
use Modules\Tax\Http\Controllers\Admin\TaxClass\ShowTaxClassController;
use Modules\Tax\Http\Controllers\Admin\TaxClass\UpdateTaxClassController;
use Modules\Tax\Http\Controllers\Admin\TaxRate\CreateTaxRateController;
use Modules\Tax\Http\Controllers\Admin\TaxRate\DeleteTaxRateController;
use Modules\Tax\Http\Controllers\Admin\TaxRate\ListTaxRateController;
use Modules\Tax\Http\Controllers\Admin\TaxRate\ShowTaxRateController;
use Modules\Tax\Http\Controllers\Admin\TaxRate\UpdateTaxRateController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tax-classes', ListTaxClassController::class)->name('tax-classes.list');
    Route::post('/tax-classes', CreateTaxClassController::class)->name('tax-classes.create');
    Route::get('/tax-classes/{id}', ShowTaxClassController::class)->name('tax-classes.show');
    Route::patch('/tax-classes/{id}', UpdateTaxClassController::class)->name('tax-classes.update');
    Route::delete('/tax-classes/{id}', DeleteTaxClassController::class)->name('tax-classes.delete');

    Route::get('/tax-rates', ListTaxRateController::class)->name('tax-rates.list');
    Route::post('/tax-rates', CreateTaxRateController::class)->name('tax-rates.create');
    Route::get('/tax-rates/{id}', ShowTaxRateController::class)->name('tax-rates.show');
    Route::patch('/tax-rates/{id}', UpdateTaxRateController::class)->name('tax-rates.update');
    Route::delete('/tax-rates/{id}', DeleteTaxRateController::class)->name('tax-rates.delete');
});
