<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Discount\Http\Controllers\Admin\Coupon\CreateCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\DeleteCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\ListCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\ShowCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\UpdateCouponController;
use Modules\Discount\Http\Controllers\Admin\Data\ExportDiscountDataController;
use Modules\Discount\Http\Controllers\Admin\Data\ImportDiscountDataController;
use Modules\Discount\Http\Controllers\Admin\Data\ListDiscountDataImportsController;
use Modules\Discount\Http\Controllers\Admin\Data\PreviewDiscountDataImportController;
use Modules\Discount\Http\Controllers\Admin\Promotion\CreatePromotionController;
use Modules\Discount\Http\Controllers\Admin\Promotion\DeletePromotionController;
use Modules\Discount\Http\Controllers\Admin\Promotion\ListPromotionController;
use Modules\Discount\Http\Controllers\Admin\Promotion\ShowPromotionController;
use Modules\Discount\Http\Controllers\Admin\Promotion\UpdatePromotionController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/coupons', ListCouponController::class)->name('coupons.list');
    Route::post('/coupons', CreateCouponController::class)->name('coupons.create');
    Route::get('/coupons/{id}', ShowCouponController::class)->name('coupons.show');
    Route::patch('/coupons/{id}', UpdateCouponController::class)->name('coupons.update');
    Route::delete('/coupons/{id}', DeleteCouponController::class)->name('coupons.delete');

    Route::get('/promotions', ListPromotionController::class)->name('promotions.list');
    Route::post('/promotions', CreatePromotionController::class)->name('promotions.create');
    Route::get('/promotions/{id}', ShowPromotionController::class)->name('promotions.show');
    Route::patch('/promotions/{id}', UpdatePromotionController::class)->name('promotions.update');
    Route::delete('/promotions/{id}', DeletePromotionController::class)->name('promotions.delete');
    // The module's own data-transfer endpoints (per-module surface from
    // the transfer standard's phase plan; the central transfer endpoints
    // remain available).
    Route::get('/data/export', ExportDiscountDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewDiscountDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportDiscountDataController::class)->name('data.import');
    Route::get('/data/imports', ListDiscountDataImportsController::class)->name('data.imports.list');
});
