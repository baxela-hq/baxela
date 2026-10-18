<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\User\ProductComment\CreateProductCommentController;
use Modules\Catalog\Http\Controllers\User\ProductComment\ListMyProductCommentReactionsController;
use Modules\Catalog\Http\Controllers\User\ProductComment\ReactToProductCommentController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::post('/products/{id}/comments', CreateProductCommentController::class)->name('product-comments.create');
    Route::get('/products/{id}/comment-reactions', ListMyProductCommentReactionsController::class)
        ->name('product-comments.my-reactions');
    Route::post('/product-comments/{id}/reaction', ReactToProductCommentController::class)
        ->name('product-comments.reaction');
});
