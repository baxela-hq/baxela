<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\User\Cart\CheckoutController;
use Modules\Cart\Http\Controllers\User\CartItem\CreateCartItemController;
use Modules\Cart\Http\Controllers\User\CartItem\DeleteCartItemController;
use Modules\Cart\Http\Controllers\User\CartItem\ListCartItemController;
use Modules\Cart\Http\Controllers\User\CartItem\UpdateCartItemController;
use Modules\Cart\Http\Controllers\User\Coupon\ApplyCouponController;
use Modules\Cart\Http\Controllers\User\Coupon\RemoveCouponController;
use Modules\Cart\Http\Controllers\User\Coupon\ShowAppliedCouponController;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::post('/checkout', CheckoutController::class)
        ->name('checkout')
        ->middleware(IdempotencyMiddleware::class);

    Route::get('/cart-items', ListCartItemController::class)->name('cart-items.list');
    Route::post('/cart-items', CreateCartItemController::class)->name('cart-items.create');
    Route::patch('/cart-items/{id}', UpdateCartItemController::class)->name('cart-items.update');
    Route::delete('/cart-items/{id}', DeleteCartItemController::class)->name('cart-items.delete');

    Route::get('/cart/coupon', ShowAppliedCouponController::class)->name('coupon.show');
    Route::post('/cart/coupon', ApplyCouponController::class)->name('coupon.apply');
    Route::delete('/cart/coupon', RemoveCouponController::class)->name('coupon.remove');
});
