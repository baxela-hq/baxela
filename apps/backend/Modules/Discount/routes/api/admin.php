<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Discount\Http\Controllers\Admin\Coupon\CreateCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\DeleteCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\ListCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\ShowCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\UpdateCouponController;
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
});
