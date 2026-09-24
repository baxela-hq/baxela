<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Discount\Http\Controllers\Admin\Coupon\CreateCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\DeleteCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\ListCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\ShowCouponController;
use Modules\Discount\Http\Controllers\Admin\Coupon\UpdateCouponController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/coupons', ListCouponController::class)->name('coupons.list');
    Route::post('/coupons', CreateCouponController::class)->name('coupons.create');
    Route::get('/coupons/{id}', ShowCouponController::class)->name('coupons.show');
    Route::patch('/coupons/{id}', UpdateCouponController::class)->name('coupons.update');
    Route::delete('/coupons/{id}', DeleteCouponController::class)->name('coupons.delete');
});
