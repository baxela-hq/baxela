<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Shipping\Http\Controllers\Admin\Method\CreateMethodController;
use Modules\Shipping\Http\Controllers\Admin\Method\DeleteMethodController;
use Modules\Shipping\Http\Controllers\Admin\Method\ListMethodController;
use Modules\Shipping\Http\Controllers\Admin\Method\ShowMethodController;
use Modules\Shipping\Http\Controllers\Admin\Method\UpdateMethodController;
use Modules\Shipping\Http\Controllers\Admin\Rate\CreateRateController;
use Modules\Shipping\Http\Controllers\Admin\Rate\DeleteRateController;
use Modules\Shipping\Http\Controllers\Admin\Rate\ListRateController;
use Modules\Shipping\Http\Controllers\Admin\Rate\ShowRateController;
use Modules\Shipping\Http\Controllers\Admin\Rate\UpdateRateController;
use Modules\Shipping\Http\Controllers\Admin\Shipment\CreateShipmentController;
use Modules\Shipping\Http\Controllers\Admin\Shipment\ListShipmentController;
use Modules\Shipping\Http\Controllers\Admin\Shipment\ShowShipmentController;
use Modules\Shipping\Http\Controllers\Admin\Shipment\UpdateShipmentController;
use Modules\Shipping\Http\Controllers\Admin\Zone\CreateZoneController;
use Modules\Shipping\Http\Controllers\Admin\Zone\DeleteZoneController;
use Modules\Shipping\Http\Controllers\Admin\Zone\ListZoneController;
use Modules\Shipping\Http\Controllers\Admin\Zone\ShowZoneController;
use Modules\Shipping\Http\Controllers\Admin\Zone\UpdateZoneController;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/methods', ListMethodController::class)->name('methods.list');
    Route::post('/methods', CreateMethodController::class)->name('methods.create');
    Route::get('/methods/{id}', ShowMethodController::class)->name('methods.show');
    Route::patch('/methods/{id}', UpdateMethodController::class)->name('methods.update');
    Route::delete('/methods/{id}', DeleteMethodController::class)->name('methods.delete');

    Route::get('/zones', ListZoneController::class)->name('zones.list');
    Route::post('/zones', CreateZoneController::class)->name('zones.create');
    Route::get('/zones/{id}', ShowZoneController::class)->name('zones.show');
    Route::patch('/zones/{id}', UpdateZoneController::class)->name('zones.update');
    Route::delete('/zones/{id}', DeleteZoneController::class)->name('zones.delete');

    Route::get('/rates', ListRateController::class)->name('rates.list');
    Route::post('/rates', CreateRateController::class)->name('rates.create');
    Route::get('/rates/{id}', ShowRateController::class)->name('rates.show');
    Route::patch('/rates/{id}', UpdateRateController::class)->name('rates.update');
    Route::delete('/rates/{id}', DeleteRateController::class)->name('rates.delete');

    Route::get('/shipments', ListShipmentController::class)->name('shipments.list');
    Route::post('/shipments', CreateShipmentController::class)->name('shipments.create');
    Route::get('/shipments/{id}', ShowShipmentController::class)->name('shipments.show');
    Route::patch('/shipments/{id}', UpdateShipmentController::class)->name('shipments.update');
});
