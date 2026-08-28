<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\User\Cart\CheckoutController;
use Modules\Cart\Http\Controllers\User\CartItem\CreateCartItemController;
use Modules\Cart\Http\Controllers\User\CartItem\DeleteCartItemController;
use Modules\Cart\Http\Controllers\User\CartItem\ListCartItemController;
use Modules\Cart\Http\Controllers\User\CartItem\UpdateCartItemController;
use Modules\Core\Http\Middleware\IdempotencyMiddleware;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::post('/checkout', CheckoutController::class)
        ->name('checkout')
        ->middleware(IdempotencyMiddleware::class);

    Route::get('/cart-items', ListCartItemController::class)->name('cart-items.list');
    Route::post('/cart-items', CreateCartItemController::class)->name('cart-items.create');
    Route::patch('/cart-items/{id}', UpdateCartItemController::class)->name('cart-items.update');
    Route::delete('/cart-items/{id}', DeleteCartItemController::class)->name('cart-items.delete');
});
