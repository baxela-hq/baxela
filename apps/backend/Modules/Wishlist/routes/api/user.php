<?php

use Illuminate\Support\Facades\Route;
use Modules\Wishlist\Http\Controllers\User\WishlistItem\CreateWishlistItemController;
use Modules\Wishlist\Http\Controllers\User\WishlistItem\DeleteWishlistItemController;
use Modules\Wishlist\Http\Controllers\User\WishlistItem\ListWishlistItemController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/wishlist-items', ListWishlistItemController::class)->name('wishlist-items.list');
    Route::post('/wishlist-items', CreateWishlistItemController::class)->name('wishlist-items.create');
    Route::delete('/wishlist-items/{product_id}', DeleteWishlistItemController::class)->name('wishlist-items.delete');
});
