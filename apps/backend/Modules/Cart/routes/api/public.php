<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\Public\CartItem\CreateCartItemController;
use Modules\Cart\Http\Controllers\Public\CartItem\DeleteCartItemController;
use Modules\Cart\Http\Controllers\Public\CartItem\ListCartItemController;
use Modules\Cart\Http\Controllers\Public\CartItem\UpdateCartItemController;
use Modules\Cart\Http\Middleware\CartTokenMiddleware;

// Guest carts: the X-Cart-Token header (validated by the middleware and
// rate-limited per token) acts as the bearer credential for the cart.
Route::middleware([CartTokenMiddleware::class, 'throttle:cart-public'])
    ->prefix('public')->name('public.')->group(function () {
        Route::get('/cart-items', ListCartItemController::class)->name('cart-items.list');
        Route::post('/cart-items', CreateCartItemController::class)->name('cart-items.create');
        Route::patch('/cart-items/{id}', UpdateCartItemController::class)->name('cart-items.update');
        Route::delete('/cart-items/{id}', DeleteCartItemController::class)->name('cart-items.delete');
    });
